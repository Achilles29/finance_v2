<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/application/libraries/DeploymentConfig.php';
try {
    if (PHP_SAPI!=='cli' || count($argv)!==2 || $argv[1]!=='check') throw new RuntimeException('USAGE_CUSTOMER_CONFIG_CHECK');
    $config=DeploymentConfig::forRoot(dirname(__DIR__,2));
    echo json_encode(['status'=>'PASS','contract'=>CustomerLocalConfig::CONTRACT,'source'=>CustomerLocalConfig::PATH,
        'database_target'=>['host'=>$config->get(DeploymentConfig::DB_HOST),'port'=>(int)$config->get(DeploymentConfig::DB_PORT),
            'name'=>$config->get(DeploymentConfig::DB_NAME)],'base_url'=>$config->get(DeploymentConfig::BASE_URL),
        'database_connection_tested'=>false,'installation_ready'=>false],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) {
    $code=preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'CUSTOMER_CONFIGURATION_UNAVAILABLE';
    fwrite(STDERR,json_encode(['status'=>'BLOCKED','code'=>$code,
        'recovery'=>'Periksa config/customer.json dan docs/customer_local_install.md; jangan mengubah kode inti.'])."\n");exit(1);
}
