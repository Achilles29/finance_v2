<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
$root = dirname(__DIR__, 2);
require $root . '/application/libraries/Module_notification.php';
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function site_url($path): string { return 'https://fixture.invalid/' . $path; }
$context = new stdClass();
function &get_instance() { return $GLOBALS['context']; }
$context->load = new class {
    public function model(string $name): void { if ($name !== 'Module_notification_model') throw new RuntimeException('Unexpected model'); }
    public function view(string $name, array $vars): void {
        if ($name !== 'notifications/settings') throw new RuntimeException('Unexpected view');
        extract($vars, EXTR_SKIP); require $GLOBALS['root'] . '/application/views/' . $name . '.php';
    }
};
$context->Module_notification_model = new class {
    public bool $schema = true;
    public bool $selected = false;
    public function ready(): bool { return $this->schema; }
    public function rules($channel): array {
        $rules = [];
        foreach (Module_notification::events($channel) as $event => $title) $rules[$event] = ['title'=>$title,'is_enabled'=>0,'targets'=>$this->selected ? array_intersect_key($this->available_targets($channel), array_flip(['group:1','group:2'])) : []];
        return $rules;
    }
    public function available_targets($channel, bool $include_unavailable = false): array {
        if ($channel === 'TELEGRAM') return ['chat:1'=>['label'=>'<img src=x onerror=alert(1)>','destination'=>'-100123']];
        $targets = ['group:1'=>['label'=>'<img src=x onerror=alert(1)>','destination'=>'1200001@g.us'], 'group:2'=>['label'=>'Grup tanpa balasan bot','destination'=>'1200002@g.us']];
        if ($include_unavailable) $targets['group:3'] = ['label'=>'Grup belum punya ID','destination'=>'','unavailable'=>true];
        return $targets;
    }
    public function recent($channel): array { return [['id'=>1,'created_at'=>'2026-09-23','event_code'=>'SELF_ORDER','source_id'=>1,'target_label'=>'<script>evil</script>','status'=>'FAILED','last_error'=>'<svg/onload=alert(1)>']]; }
};
$page = new class($context->load) {
    public $load;
    public $session;
    public function __construct($load) {
        $this->load = $load;
        $this->session = new class { public function flashdata($key) { return null; } };
    }
    public function render(bool $can_edit): string {
        $settings = []; $session = []; $wa_settings_mutation_csrf = 'fixture-value';
        ob_start(); require $GLOBALS['root'] . '/application/views/wa/settings.php'; return ob_get_clean();
    }
};
if (in_array('--wa-page-fixture', $argv, true)) {
    $context->Module_notification_model->selected = true;
    echo json_encode(['edit'=>$page->render(true),'read'=>$page->render(false)], JSON_THROW_ON_ERROR);
    exit;
}
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$render = static function (string $file, array $vars) use ($root): string {
    extract($vars, EXTR_SKIP); ob_start(); require $root . '/application/views/notifications/' . $file . '.php'; return ob_get_clean();
};
$xpath = static function (string $html): DOMXPath {
    $doc = new DOMDocument(); libxml_use_internal_errors(true); $doc->loadHTML('<?xml encoding="UTF-8">' . $html); libxml_clear_errors(); return new DOMXPath($doc);
};
foreach (['WA','TELEGRAM'] as $channel) {
    $base = ['notification_channel'=>$channel, 'notification_csrf_name'=>'fixture_csrf', 'notification_csrf'=>'fixture-value', 'notification_action'=>'fixture/save'];
    foreach ([true,false] as $edit) {
        $eventCount = count(Module_notification::events($channel));
        $html = $render('settings', $base + ['notification_can_edit'=>$edit]);
        $x = $xpath($html);
        $check($x->query('//input[@type="checkbox" and starts-with(@id,"notify-")]')->length === $eventCount, $channel . ' event switches including WA-only Daily Sales');
        $check($x->query('//select[@multiple]')->length === ($channel === 'WA' ? 0 : 3), $channel . ' WA checklist / Telegram select');
        if ($channel === 'WA') {
            $check($x->query('//input[@type="checkbox" and starts-with(@id,"target-")]')->length === $eventCount * 3, 'all three groups shown separately per module');
            $check($x->query('//input[@type="checkbox" and @disabled]')->length === $eventCount, 'invalid JID visible but cannot be selected');
            $check(strpos($html, 'Status grup di menu Grup WA hanya mengatur balasan chat bot') !== false, 'inbound/outbound distinction explained');
        }
        $check($x->query('//input[@checked]')->length === 0, $channel . ' defaults OFF');
        $check($x->query('//fieldset[@disabled]')->length === ($edit ? 0 : $eventCount), $channel . ' read-only settings respect RBAC');
        $check($x->query('//button[@type="submit"]')->length === ($edit ? 2 : 0), $channel . ' editors alone can save/retry');
        $check($x->query('//img|//script|//svg')->length === 0, $channel . ' recipients and errors escaped');
        $check(strpos($html, 'belum terdeteksi') !== false, $channel . ' absent worker clearly explained');
        $check(strpos($html, 'fixture-value') !== false, $channel . ' actual CSRF hidden value rendered');
    }
}
$context->Module_notification_model->selected = true;
foreach ([true,false] as $edit) {
    $html = $page->render($edit); $x = $xpath($html);
    $check($x->query('//input[@checked and starts-with(@id,"target-")]')->length === 8, 'multiple saved selections restored per module');
    $check($x->query('//*[@role="tab"]')->length === 4 && $x->query('//*[@role="tabpanel"]')->length === 4, 'four accessible sections');
    $check($x->query('//*[@id="wa-notifications"]//*[@id="module-notifications"]')->length === 1, 'notification panel belongs to its tab');
    $check($x->query('//*[@id="wa-connection"]//*[@id="qr-panel"]')->length === 1, 'QR panel belongs to connection tab');
    $check($x->query('//*[@id="wa-testing"]//*[@id="btn-ping"]')->length === 1, 'ping belongs to testing tab');
    $check($x->query('//*[@id="wa-technical"]//*[@id="btn-engine-refresh"]')->length === 1, 'engine belongs to technical tab');
    $check($x->query('//*[@id="env-card" or @id="btn-session-reset"]')->length === ($edit ? 2 : 0), 'technical edit controls still respect RBAC');
    $ids = []; foreach ($x->query('//*[@id]') as $node) $ids[] = $node->getAttribute('id');
    $check(count($ids) === count(array_unique($ids)), 'no duplicate IDs after splitting into tabs');
    $check($x->query('//form//form')->length === 0, 'forms not accidentally nested');
}
$context->Module_notification_model->selected = false;
$context->Module_notification_model->schema = false;
$html = $render('settings', $base + ['notification_can_edit'=>true]);
$check(strpos($html,'2026-09-23a_module_notifications.sql') !== false && $xpath($html)->query('//form')->length === 0, 'missing migration explains recovery and offers no broken form');
foreach ([[], ['WA'], ['TELEGRAM'], ['WA','TELEGRAM']] as $channels) {
    $vars = ['notification_channels'=>$channels,'notification_csrf'=>'fixture-value','notification_status'=>'SUBMITTED','notification_request_id'=>12];
    $html = $render('division_buttons', $vars);
    $x = $xpath($html);
    $check($x->query('//button[@data-module-notify]')->length === count($channels), 'buttons follow enabled channels');
    foreach ($x->query('//button') as $button) {
        $check($button->getAttribute('type') === 'button' && $button->getAttribute('data-notify-csrf') === 'fixture-value', 'button cannot submit a surrounding form and carries CSRF');
        $check($button->getAttribute('data-notify-url') === 'https://fixture.invalid/procurement/division-po-sr/notify/12', 'actual endpoint includes request ID');
    }
    $vars['notification_status'] = 'VOID';
    $check($xpath($render('division_buttons', $vars) ?: '<div></div>')->query('//button')->length === 0, 'void request has no send action');
}
$process = proc_open(['/usr/bin/node', __DIR__.'/module_notifications_client_smoke.cjs'], [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
if (!is_resource($process)) throw new RuntimeException('Could not run JS regression');
$output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
$check(proc_close($process) === 0, 'JS client regression: '.$error);
echo "Module notification UI: {$checks} HTML/DOM checks PASS. " . $output;
