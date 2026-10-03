<?php

declare(strict_types=1);

define('BASEPATH', __DIR__);
require dirname(__DIR__, 2) . '/application/libraries/Access_event_log.php';

$directory = sys_get_temp_dir() . '/finance-access-events-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    fwrite(STDERR, "FAIL: could not create isolated test directory\n");
    exit(1);
}

$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$logger = new Access_event_log($directory);
$event = [
    'event_kind' => 'PAGE_VIEW', 'event_id' => 1001,
    'event_at' => '2026-10-03 12:00:00.123456', 'user_id' => 9,
    'username' => 'tester', 'page_code' => 'dashboard', 'route_path' => 'dashboard',
    'request_method' => 'GET', 'action_label' => 'Membuka halaman',
    'module_code' => 'SYSTEM', 'ip_address' => '127.0.0.1',
    'user_agent' => 'test-agent', 'device_source' => 'ACCESS_EVENT',
];
$check($logger->append($event), 'page event is appended');
$check($logger->cutover() === $event['event_at'], 'first page event establishes a stable legacy cutover');
$event['event_id']++;
$event['event_at'] = '2026-10-03 12:00:01.123456';
$check($logger->append($event), 'subsequent page event is appended without moving cutover');
$events = iterator_to_array($logger->eventsBetween('2026-10-03 00:00:00', '2026-10-04 00:00:00'));
$check(count($events) === 2 && $events[0]['event_id'] === 1001, 'date-bounded reader returns valid JSONL events in write order');
$check((fileperms($directory) & 0777) === 0700, 'event directory is private to the PHP runtime user');

foreach (glob($directory . '/*') ?: [] as $path) unlink($path);
rmdir($directory);
