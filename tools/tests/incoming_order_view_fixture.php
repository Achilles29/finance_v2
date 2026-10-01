<?php
declare(strict_types=1);
// Render production views only; no application bootstrap or live database.
function site_url($path = '') { return 'https://fixture.invalid/' . $path; }
function base_url($path = '') { return site_url($path); }
function html_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
class IncomingOrderViewFixture
{
    public $load;
    public function __construct() { $this->load = $this; }
    public function view($view, $data = []) {
        extract($data);
        include dirname(__DIR__, 2) . '/application/views/' . $view . '.php';
    }
}
$fixture = new IncomingOrderViewFixture();
$views = [];
foreach (['self_order_orders', 'online_food_orders'] as $view) {
    ob_start();
    $fixture->view('pos/' . $view, ['filters' => ['status_tab' => 'NEEDS_VERIFY', 'date_from' => '2026-09-24', 'date_to' => '2026-09-24']]);
    $views[$view] = ob_get_clean();
}
echo json_encode($views, JSON_THROW_ON_ERROR);
