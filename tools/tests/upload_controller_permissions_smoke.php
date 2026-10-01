<?php
declare(strict_types=1);

if (!function_exists('posix_geteuid') || posix_geteuid() === 0) {
    fwrite(STDERR, "Run this test as an unprivileged PHP-FPM user.\n");
    exit(1);
}
$root = dirname(__DIR__, 2);
$fixture = sys_get_temp_dir() . '/finance-upload-permissions-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
define('BASEPATH', $root . '/system/');
define('FCPATH', $fixture . '/');
class MY_Controller { public $session; }
class UploadPermissionTestSession
{
    public $errors = [];
    public function set_flashdata($type, $message): void { $this->errors[] = [$type, $message]; }
}
require $root . '/application/controllers/Roastery.php';
require $root . '/application/controllers/Assets.php';
require $root . '/application/controllers/Master.php';
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
    echo "PASS {$message}\n";
};
$mask = umask(0027);
try {
    foreach (['Roastery' => 'ensure_label_storage_ready', 'Assets' => 'ensure_asset_storage_ready'] as $class => $method) {
        $controller = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $controller->session = new UploadPermissionTestSession();
        $invoke = new ReflectionMethod($class, $method);
        $invoke->setAccessible(true);
        $relative = 'uploads/' . strtolower($class);
        $path = FCPATH . $relative;
        $check($invoke->invoke($controller, [$relative]), "{$class}: missing directory prepared");
        clearstatcache();
        $check((fileperms($path) & 0007) === 0, "{$class}: new directory is not world-accessible");
        chmod($path, 0750);
        $check($invoke->invoke($controller, [$relative]), "{$class}: writable directory accepted");
        clearstatcache();
        $check((fileperms($path) & 0777) === 0750, "{$class}: deployment permissions preserved");
        chmod($path, 0550);
        clearstatcache();
        $check(!$invoke->invoke($controller, [$relative]), "{$class}: read-only directory rejected");
        clearstatcache();
        $check((fileperms($path) & 0777) === 0550, "{$class}: no automatic chmod on denied upload");
        $check(count($controller->session->errors) === 1, "{$class}: permission error reported");
    }
    mkdir(FCPATH . 'uploads/product', 0550);
    $_FILES['photo_file'] = ['name' => 'test.png'];
    $master = (new ReflectionClass('Master'))->newInstanceWithoutConstructor();
    $master->session = new UploadPermissionTestSession();
    $method = new ReflectionMethod('Master', 'handleProductPhotoUpload');
    $method->setAccessible(true);
    $check($method->invoke($master) === null, 'Product: read-only storage rejected before upload');
    clearstatcache();
    $check((fileperms(FCPATH . 'uploads/product') & 0777) === 0550, 'Product: no permission escalation');
    $check(count($master->session->errors) === 1, 'Product: permission error reported');
} finally {
    unset($_FILES['photo_file']);
    umask($mask);
    foreach (['roastery', 'assets', 'product'] as $name) {
        $path = FCPATH . 'uploads/' . $name;
        if (is_dir($path)) { chmod($path, 0700); rmdir($path); }
    }
    if (is_dir(FCPATH . 'uploads')) rmdir(FCPATH . 'uploads');
    rmdir($fixture);
}
echo "All {$checks} upload controller permission checks passed.\n";
