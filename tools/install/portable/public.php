<?php
declare(strict_types=1);
// This file is mapped to public/index.php ONLY in a v6 customer build.
define('FINANCE_PUBLIC_ROOT', __DIR__);
$root=dirname(__DIR__);
require_once $root.'/application/libraries/CustomerPlatform.php';
if (!CustomerPlatform::portable($root)) { http_response_code(423); exit('Identitas paket tidak tersedia. Hubungi administrator.'); }
if (PHP_SAPI!=='cli' && realpath((string)($_SERVER['DOCUMENT_ROOT']??''))!==realpath(__DIR__)) {
    http_response_code(503);exit('Arahkan root website ke folder finance/public. Jangan gunakan folder induk Finance.');
}
$route=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if ($route==='/setup' || $route==='/setup/') {
    require $root.'/tools/install/portable/setup.php';
    exit;
}
// Never permit a customer package to fall back to development if local files disappear.
if (!is_file($root.'/config/customer.json') || !is_file($root.'/storage/customer-installation.json')) {
    http_response_code(423); header('Cache-Control: no-store');
    exit('Finance belum siap. Buka /setup untuk melihat status pemasangan.');
}
chdir($root);
require $root.'/index.php';
