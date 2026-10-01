<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require dirname(__DIR__, 2) . '/application/libraries/Customer_publication.php';
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++; echo "PASS {$label}\n";
};
$check(Customer_publication::template(null) === 'legacy_namua', 'existing template preserved');
$check(Customer_publication::template('unknown') === 'disabled', 'invalid stored template cannot publish legacy customer content');
foreach (Customer_publication::TEMPLATES as $template) $check(Customer_publication::template($template) === $template, 'template ' . $template);
foreach (['javascript:alert(1)', 'ftp://customer.test/menu', 'https://user:password@customer.test/', '//other.test/', []] as $url) $check(Customer_publication::public_url($url) === '', 'unsafe website rejected');
$check(Customer_publication::public_url('https://customer.test/menu') === 'https://customer.test/menu', 'HTTPS website accepted');
$business_profile = ['display_name' => '<script>bad()</script>', 'address' => '<b>Address</b>', 'currency_code' => 'IDR', 'phone' => '123', 'website_url' => 'javascript:alert(1)'];
$items = [['category_name' => '<img src=x>', 'product_name' => '<svg onload=bad()>', 'description' => '<script>bad()</script>', 'selling_price' => '25000']];
ob_start(); require dirname(__DIR__, 2) . '/application/views/menu_book/customer.php'; $html = (string)ob_get_clean();
$check(strpos($html, '<script>bad()') === false && strpos($html, '&lt;svg onload=bad()&gt;') !== false, 'public content escaped at render');
$check(strpos($html, 'javascript:') === false && strpos($html, '25.000') !== false, 'unsafe link omitted and sale price rendered');
$check(stripos($html, 'namua') === false, 'customer template has no legacy customer identity');
$items = []; ob_start(); require dirname(__DIR__, 2) . '/application/views/menu_book/customer.php'; $empty = (string)ob_get_clean();
$check(strpos($empty, 'Menu sedang disiapkan') !== false, 'clean customer has an explicit empty state');

// Exercise the real controller routing with a fake master-data reader (no database connection).
class CI_Controller {}
class PublicationLoad {
    public array $views = [];
    public function view($path, $data): void { $this->views[] = [$path, $data]; }
    public function library($name): void {}
}
class PublicationProfile {
    public string $template = 'customer';
    public function menu_book_template(): string { return $this->template; }
    public function profile(): array { return ['display_name' => 'Customer']; }
}
class PublicationDb {
    public array $calls = [];
    public function table_exists($table): bool { return true; }
    public function field_exists($field, $table): bool { return true; }
    public function __call($method, $args) { $this->calls[] = [$method, $args]; return $method === 'result_array' ? [] : $this; }
}
function show_404(): void { throw new RuntimeException('public_not_found'); }
require dirname(__DIR__, 2) . '/application/controllers/Menu_book.php';
$controller = (new ReflectionClass(Menu_book::class))->newInstanceWithoutConstructor();
$controller->load = new PublicationLoad(); $controller->db = new PublicationDb(); $controller->Business_profile_model = new PublicationProfile();
$controller->index(); $controller->flipbook(); $controller->page('cover'); $controller->food('main_character'); $controller->beverage('namua_signatures');
$check(count($controller->load->views) === 5 && array_unique(array_column($controller->load->views, 0)) === ['menu_book/customer'], 'all public routes respect selected template');
$calls = json_encode($controller->db->calls);
$check(strpos($calls, 'pos_order') === false && strpos($calls, 'selling_price') !== false && strpos($calls, 'p.show_landing') !== false && strpos($calls, 'p.is_active') !== false, 'only explicitly published active master products read');
$controller->Business_profile_model->template = 'disabled';
try { $controller->page('cover'); throw new LogicException('disabled content leaked'); } catch (RuntimeException $e) { $check($e->getMessage() === 'public_not_found', 'disabled template blocks legacy deep links'); }
$controller->Business_profile_model->template = 'legacy_namua'; $controller->index();
$check(end($controller->load->views)[0] === 'menu_book/index', 'legacy artwork still works');

// Exercise the actual save transaction, including rollback when settings storage fails.
class CI_Model {}
require dirname(__DIR__, 2) . '/application/models/Business_profile_model.php';
function log_message($level, $message): void {}
class PublicationSaveDb {
    public bool $failSetting = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public array $audit = [];
    public array $settings = [];
    public function table_exists($name): bool { return true; }
    public function from($name) { return $this; }
    public function where($name, $value) { return $this; }
    public function limit($count) { return $this; }
    public function count_all_results(): int { return 1; }
    public function trans_begin(): void {}
    public function update($table, $data): bool { return true; }
    public function query($sql, $params): bool { $this->settings = $params; return !$this->failSetting; }
    public function insert($table, $data): bool { $this->audit = $data; return true; }
    public function trans_status(): bool { return true; }
    public function trans_commit(): void { $this->committed = true; }
    public function trans_rollback(): void { $this->rolledBack = true; }
}
class PublicationSaveProfile extends Business_profile_model {
    public function profile(): array { return ['display_name' => 'Old customer']; }
    public function menu_book_template(): string { return 'legacy_namua'; }
}
$profileModel = new PublicationSaveProfile(); $profileModel->load = new PublicationLoad(); $profileModel->db = new PublicationSaveDb();
$input = ['display_name' => 'New customer', 'menu_book_template' => 'customer'];
$result = $profileModel->save($input, 1);
$check($result['ok'] && $profileModel->db->committed && $profileModel->db->settings[2] === 'customer', 'template and profile committed together');
$check(json_decode($profileModel->db->audit['before_json'], true)['menu_book_template'] === 'legacy_namua'
    && json_decode($profileModel->db->audit['after_json'], true)['menu_book_template'] === 'customer', 'template choice included in existing before/after audit');
$profileModel->db = new PublicationSaveDb(); $profileModel->db->failSetting = true;
$result = $profileModel->save($input, 1);
$check(!$result['ok'] && $profileModel->db->rolledBack && !$profileModel->db->committed && $profileModel->db->audit === [], 'settings failure rolls back profile instead of reporting success');
$profileModel->db = new PublicationSaveDb(); $input['menu_book_template'] = 'invalid';
$check(!$profileModel->save($input, 1)['ok'] && !$profileModel->db->committed, 'invalid template rejected before write');
echo "All {$checks} customer publication checks passed.\n";
