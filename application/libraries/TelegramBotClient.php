<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class TelegramBotClient
{
    private const API_BASE = 'https://api.telegram.org';
    private const ALLOWED_METHODS = ['sendMessage', 'getMe', 'getWebhookInfo', 'getUpdates', 'setWebhook'];
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const REQUEST_TIMEOUT_SECONDS = 8;
    private const MAX_MESSAGE_BYTES = 4096;
    private const MAX_RESPONSE_BYTES = 65536;
    private const MAX_DISCOVERY_RESPONSE_BYTES = 262144;

    public function is_configured(): bool { return trim((string)getenv('FINANCE_TELEGRAM_BOT_TOKEN')) !== ''; }
    public function is_secret_configured(): bool { return self::is_valid_secret((string)getenv('FINANCE_TELEGRAM_WEBHOOK_SECRET')); }
    public static function is_valid_secret(string $secret): bool { return preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $secret) === 1; }

    public function configured_webhook_url(): string
    {
        $url = (string)getenv('FINANCE_TELEGRAM_WEBHOOK_URL');
        return self::validate_webhook_url($url) ? $url : '';
    }

    public static function validate_webhook_url(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url) === 1) return false;
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('#/telegram_webhook$#D', (string)($parts['path'] ?? '')) !== 1) return false;
        $host = strtolower(trim((string)$parts['host'], '[]'));
        if ($host === 'localhost' || substr($host, -10) === '.localhost' || substr($host, -6) === '.local') return false;
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host) === 1;
    }

    public function get_me(): array
    {
        $r = $this->request('getMe', []);
        $bot = is_array($r['result']) ? $r['result'] : [];
        $id = is_int($bot['id'] ?? null) ? $bot['id'] : 0;
        $username = self::clean_text($bot['username'] ?? '', 64);
        if (!$r['ok'] || $id <= 0 || $username === '') return ['ok'=>false,'bot'=>null,'error'=>'Identitas bot Telegram tidak dapat diverifikasi.'];
        return ['ok'=>true,'bot'=>['id'=>$id,'username'=>$username,'first_name'=>self::clean_text($bot['first_name'] ?? '',150)],'error'=>''];
    }

    public function get_webhook_info(): array
    {
        $r = $this->request('getWebhookInfo', []);
        if (!$r['ok'] || !is_array($r['result'])) return ['ok'=>false,'webhook'=>null,'error'=>'Status webhook Telegram tidak dapat diverifikasi.'];
        $remoteConfigured = is_string($r['result']['url'] ?? null) && $r['result']['url'] !== '';
        $canonicalUrl = $this->configured_webhook_url();
        return ['ok'=>true,'webhook'=>[
            'configured'=>$remoteConfigured,
            'url_matches_configured'=>$remoteConfigured && $canonicalUrl !== '' && hash_equals($canonicalUrl, (string)$r['result']['url']),
            'pending_update_count'=>max(0,(int)($r['result']['pending_update_count'] ?? 0)),
            'has_custom_certificate'=>!empty($r['result']['has_custom_certificate']),
        ],'error'=>''];
    }

    public function discover_targets(): array
    {
        $hook = $this->get_webhook_info();
        if (!$hook['ok']) return ['ok'=>false,'targets'=>[],'error'=>'Discovery target Telegram tidak dapat dijalankan.'];
        if (!empty($hook['webhook']['configured'])) return ['ok'=>false,'targets'=>[],'error'=>'Discovery hanya tersedia saat webhook belum terpasang.'];
        $r = $this->request('getUpdates', ['timeout'=>0,'limit'=>100,'allowed_updates'=>['message','channel_post']], self::MAX_DISCOVERY_RESPONSE_BYTES);
        if (!$r['ok'] || !is_array($r['result'])) return ['ok'=>false,'targets'=>[],'error'=>'Discovery target Telegram tidak dapat dijalankan.'];
        $highest = null;
        foreach ($r['result'] as $update) if (is_array($update) && is_int($update['update_id'] ?? null) && $update['update_id'] >= 0) $highest = max($highest ?? 0, $update['update_id']);
        $targets = self::sanitize_discovery_updates($r['result']);
        if ($highest !== null) {
            $consume = $this->request('getUpdates', ['offset'=>$highest + 1,'timeout'=>0,'limit'=>1,'allowed_updates'=>['message','channel_post']], self::MAX_DISCOVERY_RESPONSE_BYTES);
            if (!$consume['ok']) return ['ok'=>false,'targets'=>[],'error'=>'Discovery target Telegram tidak dapat diselesaikan.'];
        }
        return ['ok'=>true,'targets'=>$targets,'error'=>''];
    }

    public static function sanitize_discovery_updates(array $updates): array
    {
        $targets=[];$seen=[];$types=['group'=>'GROUP','supergroup'=>'SUPERGROUP','channel'=>'CHANNEL'];
        foreach ($updates as $update) {
            if (!is_array($update)) continue;
            $message = is_array($update['message'] ?? null) ? $update['message'] : (is_array($update['channel_post'] ?? null) ? $update['channel_post'] : null);
            $chat = is_array($message) && is_array($message['chat'] ?? null) ? $message['chat'] : null;
            $type = strtolower((string)($chat['type'] ?? ''));
            $id = self::negative_chat_id($chat['id'] ?? null);
            $title = self::clean_text($chat['title'] ?? '', 150);
            if (!isset($types[$type]) || $id === null || $title === '' || isset($seen[$id])) continue;
            $seen[$id]=true;$targets[]=['chat_id'=>$id,'target_type'=>$types[$type],'title'=>$title];
            if (count($targets) === 20) break;
        }
        return $targets;
    }

    public function install_webhook(): array
    {
        $url=$this->configured_webhook_url();$secret=(string)getenv('FINANCE_TELEGRAM_WEBHOOK_SECRET');
        if (!$this->is_configured() || $url==='' || !self::is_valid_secret($secret)) return ['ok'=>false,'error'=>'Konfigurasi webhook Telegram belum valid.'];
        $before=$this->get_webhook_info();
        if (!$before['ok']) return ['ok'=>false,'error'=>'Webhook Telegram tidak dapat dipasang.'];
        $firstInstall=empty($before['webhook']['configured']);
        $r=$this->request('setWebhook',['url'=>$url,'secret_token'=>$secret,'allowed_updates'=>['message','channel_post'],'drop_pending_updates'=>$firstInstall]);
        if (!$r['ok'] || $r['result'] !== true) return ['ok'=>false,'error'=>'Webhook Telegram tidak dapat dipasang.'];
        $verified=$this->get_webhook_info();
        return $verified['ok'] && !empty($verified['webhook']['url_matches_configured'])
            ? ['ok'=>true,'error'=>''] : ['ok'=>false,'error'=>'Webhook Telegram tidak dapat diverifikasi.'];
    }

    public function send_message(string $chatId, string $message): array
    {
        $chatId=trim($chatId);$message=trim($message);
        if (!$this->is_configured()) return $this->failure('FAILED','Token Telegram belum dikonfigurasi.');
        if (preg_match('/^-?[0-9]{1,20}$/D',$chatId)!==1) return $this->failure('FAILED','Chat ID Telegram tidak valid.');
        if ($message==='' || strlen($message)>self::MAX_MESSAGE_BYTES) return $this->failure('FAILED','Pesan Telegram kosong atau melebihi 4096 byte.');
        $r=$this->request('sendMessage',['chat_id'=>$chatId,'text'=>$message,'disable_web_page_preview'=>true]);
        if ($r['transport']==='timeout') return $this->failure('UNKNOWN','Status pengiriman tidak diketahui karena timeout.',$r['http_code']);
        if ($r['transport']==='ambiguous') return $this->failure('UNKNOWN','Status pengiriman tidak diketahui setelah request dikirim.',$r['http_code']);
        if (!$r['ok'] || !is_array($r['result'])) return $this->failure('FAILED','Telegram Bot API menolak pesan.',$r['http_code']);
        return ['status'=>'SENT','ok'=>true,'http_code'=>$r['http_code'],'message_id'=>isset($r['result']['message_id'])?(int)$r['result']['message_id']:null,'error'=>''];
    }

    private function request(string $method,array $payload,int $maxBytes=self::MAX_RESPONSE_BYTES): array
    {
        $token=trim((string)getenv('FINANCE_TELEGRAM_BOT_TOKEN'));
        if ($token==='' || !in_array($method,self::ALLOWED_METHODS,true) || !function_exists('curl_init')) return ['ok'=>false,'http_code'=>0,'result'=>null,'transport'=>'failed'];
        $maxBytes=$method==='getUpdates'?self::MAX_DISCOVERY_RESPONSE_BYTES:self::MAX_RESPONSE_BYTES;
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $curl=is_string($json)?curl_init(self::API_BASE.'/bot'.rawurlencode($token).'/'.$method):false;
        if ($curl===false) return ['ok'=>false,'http_code'=>0,'result'=>null,'transport'=>'failed'];
        $body='';$tooLarge=false;
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body,&$tooLarge,$maxBytes):int{if(strlen($body)+strlen($chunk)>$maxBytes){$tooLarge=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
        $executed=curl_exec($curl);$errno=curl_errno($curl);$http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$sent=(int)curl_getinfo($curl,CURLINFO_REQUEST_SIZE);curl_close($curl);
        if ($errno===CURLE_OPERATION_TIMEDOUT) return ['ok'=>false,'http_code'=>$http,'result'=>null,'transport'=>'timeout'];
        if ($tooLarge || ($errno!==0 && $sent>0)) return ['ok'=>false,'http_code'=>$http,'result'=>null,'transport'=>'ambiguous'];
        if ($errno!==0 || $executed!==true || $http<200 || $http>=300) return ['ok'=>false,'http_code'=>$http,'result'=>null,'transport'=>'failed'];
        $decoded=json_decode($body,true);
        if (!is_array($decoded) || ($decoded['ok']??null)!==true || !array_key_exists('result',$decoded)) return ['ok'=>false,'http_code'=>$http,'result'=>null,'transport'=>'failed'];
        return ['ok'=>true,'http_code'=>$http,'result'=>$decoded['result'],'transport'=>'ok'];
    }

    private static function negative_chat_id($value): ?string
    {
        if (is_int($value)) $value=(string)$value;
        return is_string($value) && preg_match('/^-[1-9][0-9]{0,18}$/D',$value)===1?$value:null;
    }
    private static function clean_text($value,int $max): string
    {
        if (!is_string($value)) return '';
        $value=preg_replace('/[\x00-\x1F\x7F]/','',$value);
        return is_string($value)?mb_substr(trim($value),0,$max,'UTF-8'):'';
    }
    private function failure(string $status,string $message,int $http=0): array
    {
        return ['status'=>$status,'ok'=>false,'http_code'=>$http,'message_id'=>null,'error'=>$message];
    }
}
