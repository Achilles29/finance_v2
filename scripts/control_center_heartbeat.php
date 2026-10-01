<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from CLI.\n");
    exit(1);
}

const FINANCE_ROOT = __DIR__.'/..';
const HEARTBEAT_CONFIG = '/var/lib/finance-config/control-center-heartbeat.json';
const PRIVATE_DATABASE_CONFIG = '/var/lib/finance-config/database.php';
require_once dirname(__DIR__).'/application/libraries/DeploymentConfig.php';
require_once dirname(__DIR__).'/application/libraries/Control_license_cache.php';
require_once dirname(__DIR__).'/tools/licensing/LicenseAgentFiles.php';

/** Capture only deployment selectors/DB fields; never emit these values in output. */
function heartbeat_deployment_environment(): array
{
    $environment=[];
    foreach(['FINANCE_DEPLOYMENT_FILE','FINANCE_CUSTOMER_INSTALLATION_FILE','FINANCE_HEARTBEAT_CONFIG_FILE',
        DeploymentConfig::DB_HOST,DeploymentConfig::DB_NAME,DeploymentConfig::DB_USER,DeploymentConfig::DB_PASSWORD] as $name){
        $value=getenv($name);
        if($value!==false)$environment[$name]=$value;
    }
    return $environment;
}

function heartbeat_config_path(?array $environment=null): string
{
    if($environment===null){
        $value=getenv('FINANCE_HEARTBEAT_CONFIG_FILE');
        $environment=$value===false?[]:['FINANCE_HEARTBEAT_CONFIG_FILE'=>$value];
    }
    if(!array_key_exists('FINANCE_HEARTBEAT_CONFIG_FILE',$environment))return HEARTBEAT_CONFIG;
    $path=$environment['FINANCE_HEARTBEAT_CONFIG_FILE'];
    if(!is_string($path)||$path===''||$path[0]!=='/'||strpos($path,"\0")!==false)throw new RuntimeException('HEARTBEAT_CONFIG_PATH_INVALID');
    return $path;
}

/** Any explicit customer/deployment selection prohibits the legacy master DB fallback. */
function heartbeat_explicit_database(array $environment): bool
{
    foreach(['FINANCE_DEPLOYMENT_FILE','FINANCE_CUSTOMER_INSTALLATION_FILE','FINANCE_HEARTBEAT_CONFIG_FILE',
        DeploymentConfig::DB_HOST,DeploymentConfig::DB_NAME,DeploymentConfig::DB_USER,DeploymentConfig::DB_PASSWORD] as $name)
        if(array_key_exists($name,$environment))return true;
    return false;
}

/** Pure mapping of this installation's production contract to the read-only sender. */
function heartbeat_deployment_database(DeploymentConfig $deployment): array
{
    $config=[];
    foreach(['hostname'=>DeploymentConfig::DB_HOST,'database'=>DeploymentConfig::DB_NAME,
        'username'=>DeploymentConfig::DB_USER,'password'=>DeploymentConfig::DB_PASSWORD] as $key=>$name){
        $value=$deployment->get($name,'');
        if(!is_string($value)||trim($value)===''||strpos($value,"\0")!==false)throw new RuntimeException('HEARTBEAT_DATABASE_CONFIG_INCOMPLETE');
        $config[$key]=$value;
    }
    $host=$config['hostname'];
    if(strlen($host)>255||preg_match('/[\x00-\x20\x7f;]/',$host)
        ||(filter_var($host,FILTER_VALIDATE_IP)===false&&preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/iD',$host)!==1)
        ||preg_match('/\A[A-Za-z0-9_]{1,64}\z/D',$config['database'])!==1)throw new RuntimeException('HEARTBEAT_DATABASE_ENDPOINT_INVALID');
    return $config;
}

/** Context has already been verified by customer_context(), independent of license state. */
function heartbeat_release_version(string $root, ?array $context): string
{
    if($context===null)return heartbeat_git_version($root);
    if(($context['purpose']??'')!=='FINANCE_CUSTOMER_INSTALLATION'||($context['product_code']??'')!=='NAMUA_FINANCE'
        ||($context['release_root']??null)!==realpath($root)||!is_string($context['version']??null)
        ||strlen($context['version'])>50||preg_match('/\A\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?\z/D',$context['version'])!==1)
        throw new RuntimeException('HEARTBEAT_RELEASE_VERSION_INVALID');
    return $context['version'];
}

/** Only explicitly selected monitoring metadata, never an HTTP request host or registry domain. */
function heartbeat_runtime(array $config, ?string $baseUrl): ?array
{
    if(!array_key_exists('runtime_fields',$config))return null; // Old senders/configs keep registry metadata.
    $fields=$config['runtime_fields'];
    if(!is_array($fields))throw new RuntimeException('RUNTIME_FIELDS_INVALID');
    foreach($fields as $field)if(!is_string($field))throw new RuntimeException('RUNTIME_FIELDS_INVALID');
    if(!is_array($fields)||array_diff($fields,['primary_domain','region'])||count(array_unique($fields))!==count($fields))throw new RuntimeException('RUNTIME_FIELDS_INVALID');
    $runtime=[];
    if(in_array('primary_domain',$fields,true)){
        $url=$baseUrl??($config['public_url']??'');
        $host='';
        if($url!==''){
            $url=DeploymentConfig::fromSnapshot(['FINANCE_BASE_URL'=>$url])->canonicalBaseUrl('',true);
            $host=strtolower(rtrim(trim((string)parse_url($url,PHP_URL_HOST),'[]'),'.'));
            if(strlen($host)>190||preg_match('/[\x00-\x20\x7f]/',$host)
                ||(filter_var($host,FILTER_VALIDATE_IP)===false&&(preg_match('/^[0-9.]+$/D',$host)||preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/D',$host)!==1)))throw new RuntimeException('RUNTIME_DOMAIN_INVALID');
        }
        $runtime['primary_domain']=$host;
    }
    if(in_array('region',$fields,true)){
        $region=$config['region']??'';
        if(!is_string($region)||strlen($region)>320||preg_match('//u',$region)!==1||preg_match_all('/./us',$region)>80||preg_match('/\p{Cc}/u',$region))throw new RuntimeException('RUNTIME_REGION_INVALID');
        $runtime['region']=$region;
    }
    return $runtime;
}

function heartbeat_fail(string $code, string $message): void
{
    fwrite(STDERR, json_encode(['status'=>'error','code'=>$code,'message'=>$message], JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}

function heartbeat_private_json(string $path): array
{
    try{LicenseAgentFiles::securePath($path,dirname(__DIR__));}
    catch(Throwable $error){heartbeat_fail('config_permissions','Heartbeat configuration must be root-owned outside the application, without writable parent directories.');}
    if (!is_file($path) || is_link($path)) {
        heartbeat_fail('config_missing', 'Private heartbeat configuration is unavailable.');
    }
    $stat = @stat($path);
    if (!is_array($stat) || (($stat['mode'] ?? 0) & 0027) !== 0) {
        heartbeat_fail('config_permissions', 'Private heartbeat configuration permissions are unsafe.');
    }
    try {
        $config = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        heartbeat_fail('config_invalid', 'Private heartbeat configuration is malformed.');
    }
    if (!is_array($config)) heartbeat_fail('config_invalid', 'Private heartbeat configuration is malformed.');
    foreach (['endpoint','public_url','instance_id','key_id','secret','environment'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || trim($config[$key]) === '') {
            heartbeat_fail('config_invalid', 'Private heartbeat configuration is incomplete.');
        }
    }
    if (!str_starts_with($config['endpoint'], 'https://') || !str_starts_with($config['public_url'], 'https://')) {
        heartbeat_fail('config_invalid', 'Heartbeat endpoints must use HTTPS.');
    }
    // Same customer-local HTTPS URL rules used by the application, without request-host fallback.
    try {
        DeploymentConfig::fromSnapshot(['FINANCE_BASE_URL'=>$config['public_url']])->canonicalBaseUrl('',true);
        DeploymentConfig::fromSnapshot(['FINANCE_BASE_URL'=>$config['endpoint']])->canonicalBaseUrl('',true);
    }
    catch(Throwable $error){heartbeat_fail('config_invalid','Local base URL must be a valid HTTPS URL.');}
    return $config;
}

function heartbeat_database_config(?DeploymentConfig $deployment=null, ?array $environment=null): array
{
    $environment=$environment??heartbeat_deployment_environment();
    $deployment=$deployment??new DeploymentConfig();
    if(heartbeat_explicit_database($environment)||$deployment->isLocal()){
        // A blank selector must not silently select a different installation.
        foreach(['FINANCE_DEPLOYMENT_FILE','FINANCE_CUSTOMER_INSTALLATION_FILE','FINANCE_HEARTBEAT_CONFIG_FILE'] as $name)
            if(array_key_exists($name,$environment)&&(!is_string($environment[$name])||$environment[$name]===''))throw new RuntimeException('HEARTBEAT_DATABASE_CONFIG_INCOMPLETE');
        $result=heartbeat_deployment_database($deployment);
        if ($deployment->get(DeploymentConfig::DB_PORT)!==null) $result['port']=(int)$deployment->get(DeploymentConfig::DB_PORT);
        if ($deployment->get(DeploymentConfig::DB_SOCKET)!==null) $result['socket']=$deployment->get(DeploymentConfig::DB_SOCKET);
        return $result;
    }
    if (!is_file(PRIVATE_DATABASE_CONFIG) || is_link(PRIVATE_DATABASE_CONFIG)) {
        heartbeat_fail('database_config', 'Private database configuration is unavailable.');
    }
    $stat = @stat(PRIVATE_DATABASE_CONFIG);
    if (!is_array($stat) || (($stat['mode'] ?? 0) & 0007) !== 0) {
        heartbeat_fail('database_config', 'Private database configuration permissions are unsafe.');
    }
    if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'development');
    $db = [];
    include PRIVATE_DATABASE_CONFIG;
    $config = $db['default'] ?? null;
    if (!is_array($config)) heartbeat_fail('database_config', 'Private database configuration is unavailable.');
    foreach (['hostname','username','password','database'] as $key) {
        if (!isset($config[$key]) || trim((string)$config[$key]) === '') {
            heartbeat_fail('database_config', 'Private database configuration is incomplete.');
        }
    }
    return $config;
}

function heartbeat_table_exists(PDO $database, string $table): bool
{
    $query = $database->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $query->execute([$table]);
    return (int)$query->fetchColumn() === 1;
}

function heartbeat_git_version(string $root): string
{
    $git = $root.'/.git';
    $headPath = $git.'/HEAD';
    if (!is_file($headPath)) return 'unversioned';
    $head = trim((string)file_get_contents($headPath));
    $hash = $head;
    if (str_starts_with($head, 'ref: ')) {
        $ref = substr($head, 5);
        $refPath = $git.'/'.$ref;
        $hash = is_file($refPath) ? trim((string)file_get_contents($refPath)) : '';
        if ($hash === '' && is_file($git.'/packed-refs')) {
            foreach (file($git.'/packed-refs', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if ($line[0] !== '#' && substr($line, 41) === $ref) {
                    $hash = substr($line, 0, 40);
                    break;
                }
            }
        }
    }
    return preg_match('/^[a-f0-9]{40}$/D', $hash) === 1 ? 'git-'.substr($hash, 0, 12) : 'unversioned';
}

function heartbeat_http_status(string $url): string
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Namua-Finance-Heartbeat/1.0',
    ]);
    curl_exec($handle);
    $error = curl_errno($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return $error === 0 && $status >= 200 && $status < 400 ? 'OK' : 'DOWN';
}

function heartbeat_backup_status(): string
{
    $latest = 0;
    foreach (glob(FINANCE_ROOT.'/backup/dumps/*') ?: [] as $path) {
        if (is_file($path) && !is_link($path)) $latest = max($latest, (int)filemtime($path));
    }
    if ($latest === 0) return 'UNKNOWN';
    return $latest >= time() - 172800 ? 'OK' : 'DEGRADED';
}

function heartbeat_random_token(int $bytes): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

if(defined('FINANCE_HEARTBEAT_LIBRARY_ONLY')&&FINANCE_HEARTBEAT_LIBRARY_ONLY)return;

try{$configPath=heartbeat_config_path();}
catch(Throwable $error){heartbeat_fail('config_invalid','Check the private heartbeat configuration path.');}
$config = heartbeat_private_json($configPath);
try{
    $deployment=new DeploymentConfig();
    $customerContext=null;
    $environment=$deployment->customerEnvironment(FINANCE_ROOT,
        ['FINANCE_CUSTOMER_INSTALLATION_FILE'=>(string)getenv('FINANCE_CUSTOMER_INSTALLATION_FILE')]);
    $contextFile=$environment['FINANCE_CUSTOMER_INSTALLATION_FILE']??'';
    if($contextFile!==''){
        $customerContext=Control_license_cache::customer_context(FINANCE_ROOT,$contextFile);
        if(($customerContext['identity']['instance_id']??null)!==$config['instance_id'])throw new RuntimeException('HEARTBEAT_CUSTOMER_INSTANCE_MISMATCH');
    }
    $appVersion=heartbeat_release_version(FINANCE_ROOT,$customerContext);
    $localUrl=$deployment->get(DeploymentConfig::BASE_URL,$config['public_url']);
    $runtime=heartbeat_runtime($config,$localUrl);
    if($localUrl!=='')$localUrl=DeploymentConfig::fromSnapshot(['FINANCE_BASE_URL'=>$localUrl])->canonicalBaseUrl('',true);
}catch(Throwable $error){heartbeat_fail('runtime_metadata_invalid','Check the verified customer context, instance identity, trusted local base URL and monitoring metadata.');}
$components = [
    'database' => 'UNKNOWN',
    'http' => heartbeat_http_status($localUrl!==''?$localUrl:$config['public_url']),
    'schema_migration' => 'UNKNOWN',
    'runtime_queue' => 'UNKNOWN',
    'backup' => heartbeat_backup_status(),
];
$metrics = ['queue_pending'=>0, 'queue_failed'=>0];
$schemaVersion = '';

try {
    $dbConfig = heartbeat_database_config($deployment);
    $database = new PDO(
        'mysql:'.(!empty($dbConfig['socket'])?'unix_socket='.$dbConfig['socket']:'host='.$dbConfig['hostname'].';port='.($dbConfig['port']??3306))
            .';dbname='.$dbConfig['database'].';charset=utf8mb4',
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>5]
    );
    $database->query('SELECT 1')->fetchColumn();
    $components['database'] = 'OK';

    if (heartbeat_table_exists($database, 'sys_schema_migration')) {
        $latestMigration = $database->query('SELECT migration_id FROM sys_schema_migration ORDER BY applied_at DESC LIMIT 1')->fetchColumn();
        $schemaVersion = is_string($latestMigration) ? substr($latestMigration, 0, 80) : '';
        $components['schema_migration'] = $schemaVersion !== '' ? 'OK' : 'DEGRADED';
    }

    $queueTables = [
        'pos_runtime_job' => ['QUEUED','PROCESSING'],
        'pos_product_availability_queue' => ['QUEUED','PROCESSING'],
        'tg_delivery_queue' => ['PENDING','PROCESSING'],
    ];
    $knownQueue = false;
    foreach ($queueTables as $table => $pendingStates) {
        if (!heartbeat_table_exists($database, $table)) continue;
        $knownQueue = true;
        $quotedStates = implode(',', array_fill(0, count($pendingStates), '?'));
        $query = $database->prepare("SELECT SUM(status IN ({$quotedStates})) AS pending_count, SUM(status = 'FAILED') AS failed_count FROM `{$table}`");
        $query->execute($pendingStates);
        $row = $query->fetch(PDO::FETCH_ASSOC) ?: [];
        $metrics['queue_pending'] += (int)($row['pending_count'] ?? 0);
        $metrics['queue_failed'] += (int)($row['failed_count'] ?? 0);
    }
    if ($knownQueue) $components['runtime_queue'] = $metrics['queue_failed'] > 0 ? 'DEGRADED' : 'OK';
} catch (Throwable $error) {
    $components['database'] = 'DOWN';
}

$total = @disk_total_space(FINANCE_ROOT);
$free = @disk_free_space(FINANCE_ROOT);
if (is_float($total) && is_float($free) && $total > 0) {
    $metrics['disk_used_percent'] = round((($total - $free) / $total) * 100, 2);
}

$health = 'OK';
if (in_array('DOWN', $components, true) || (($metrics['disk_used_percent'] ?? 0) >= 95)) {
    $health = 'DOWN';
} elseif (in_array('DEGRADED', $components, true) || (($metrics['disk_used_percent'] ?? 0) >= 85)) {
    $health = 'DEGRADED';
}

$timestamp = gmdate('Y-m-d\TH:i:s\Z');
$nonce = heartbeat_random_token(18);
$idempotencyKey = 'hb-'.bin2hex(random_bytes(16));
$payload = [
    'instance_id' => $config['instance_id'],
    'sent_at' => $timestamp,
    'app_version' => $appVersion,
    'schema_version' => $schemaVersion,
    'environment' => $config['environment'],
    'health' => $health,
    'components' => $components,
    'metrics' => $metrics,
];
if($runtime!==null)$payload['runtime']=$runtime?:new stdClass();
$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$payloadHash = hash('sha256', $body);
$canonical = implode("\n", ['POST','/api/v1/heartbeats',$config['instance_id'],$config['key_id'],$timestamp,$nonce,$idempotencyKey,$payloadHash]);
$signature = hash_hmac('sha256', $canonical, $config['secret']);

$responseCode = 0;
$responseBody = '';
for ($attempt = 1; $attempt <= 2; $attempt++) {
    $handle = curl_init($config['endpoint']);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Namua-Instance-ID: '.$config['instance_id'],
            'X-Namua-Key-ID: '.$config['key_id'],
            'X-Namua-Timestamp: '.$timestamp,
            'X-Namua-Nonce: '.$nonce,
            'X-Namua-Signature: '.$signature,
            'Idempotency-Key: '.$idempotencyKey,
            'User-Agent: Namua-Finance-Heartbeat/1.0',
        ],
    ]);
    $responseBody = (string)curl_exec($handle);
    $curlError = curl_errno($handle);
    $responseCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    if ($curlError === 0 && $responseCode < 500) break;
}

$response = json_decode($responseBody, true);
if (!in_array($responseCode, [200,202], true) || !is_array($response) || ($response['status'] ?? '') !== 'accepted') {
    heartbeat_fail('delivery_failed', 'Control Center rejected or did not receive the heartbeat.');
}

fwrite(STDOUT, json_encode([
    'status'=>'ok',
    'control_http_status'=>$responseCode,
    'health'=>$health,
    'duplicate'=>(bool)($response['duplicate'] ?? false),
], JSON_UNESCAPED_SLASHES).PHP_EOL);
