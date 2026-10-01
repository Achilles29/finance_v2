<?php

declare(strict_types=1);

/** Landing Page content writers must remain POST+CSRF guarded. */

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Landing_page.php');
$view = (string)file_get_contents($root . '/application/views/landing_page/index.php');
$checks = 0;
$failures = [];

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$method = static function (string $name) use ($controller): string {
    $start = strpos($controller, 'function ' . $name . '(');
    if ($start === false) return '';
    $brace = strpos($controller, '{', $start);
    if ($brace === false) return '';
    $depth = 0;
    for ($i = $brace, $length = strlen($controller); $i < $length; $i++) {
        if ($controller[$i] === '{') $depth++;
        if ($controller[$i] === '}' && --$depth === 0) return substr($controller, $start, $i - $start + 1);
    }
    return '';
};

$before = static function (string $source, string $first, string $second): bool {
    $firstAt = strpos($source, $first);
    $secondAt = strpos($source, $second);
    return $firstAt !== false && $secondAt !== false && $firstAt < $secondAt;
};

$check($controller !== '' && $view !== '', 'Landing Page controller and view are readable');
$check(
    strpos($controller, "private const MUTATION_CSRF_SESSION_KEY = 'landing_page_mutation_csrf';") !== false
        && strpos($controller, "private const MUTATION_CSRF_FORM_FIELD = 'landing_page_mutation_csrf';") !== false
        && strpos($controller, "private const MUTATION_CSRF_HEADER = 'X-Landing-Page-Csrf';") !== false,
    'Landing Page owns a scoped CSRF namespace rather than reusing an unrelated token'
);

$guard = $method('require_landing_mutation');
$issuer = $method('landing_page_mutation_csrf');
$check(
    strpos($guard, "input->method(true) !== 'POST'") !== false
        && strpos($guard, 'get_request_header(self::MUTATION_CSRF_HEADER, true)') !== false
        && strpos($guard, 'input->post(self::MUTATION_CSRF_FORM_FIELD, true)') !== false
        && strpos($guard, 'hash_equals($expected, $provided)') !== false
        && strpos($guard, 'json_error') !== false,
    'mutation guard requires POST and an exact header/form CSRF token before any writer'
);
$check(
    strpos($issuer, 'random_bytes(32)') !== false
        && strpos($issuer, 'session->set_userdata(self::MUTATION_CSRF_SESSION_KEY, $token)') !== false,
    'CSRF issuer creates and stores a cryptographically random session token'
);

$writers = [
    'config_update' => 'upsert_config(',
    'menu_store' => 'add_product_to_landing(',
    'menu_delete' => 'remove_product_from_landing(',
    'menu_toggle' => 'toggle_landing_product(',
    'gallery_store' => 'insert_gallery(',
    'gallery_update' => 'update_gallery(',
    'gallery_delete' => 'delete_gallery(',
    'gallery_toggle' => 'toggle_gallery(',
    'gallery_reorder' => 'reorder_gallery(',
    'embed_store' => 'insert_embed(',
    'embed_update' => 'update_embed(',
    'embed_delete' => 'delete_embed(',
    'embed_toggle' => 'toggle_embed(',
    'links_store' => 'insert_link(',
    'links_update' => 'update_link(',
    'links_delete' => 'delete_link(',
    'links_toggle' => 'toggle_link(',
    'links_reorder' => 'reorder_links(',
];
foreach ($writers as $name => $writer) {
    $source = $method($name);
    $check(
        $before($source, 'require_permission(', 'require_landing_mutation()')
            && $before($source, 'require_landing_mutation()', $writer),
        $name . ' checks permission and scoped POST+CSRF before ' . $writer
    );
}

$check(
    strpos($view, 'name="landing_page_mutation_csrf"') !== false
        && strpos($view, 'value="<?= html_escape($landingPageMutationCsrf) ?>"') !== false,
    'configuration form submits the scoped CSRF token'
);
$check(
    strpos($view, "'X-Landing-Page-Csrf': LANDING_PAGE_MUTATION_CSRF") !== false
        && strpos($view, "fd.append('landing_page_mutation_csrf', LANDING_PAGE_MUTATION_CSRF)") !== false
        && strpos($view, 'Object.assign({}, request, { headers: headers })') !== false,
    'AJAX form and JSON writers retain the scoped CSRF header when custom headers are present'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Landing Page mutation guard check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' Landing Page mutation guard checks passed.' . PHP_EOL;
