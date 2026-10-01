<?php
declare(strict_types=1);

// DB-free behavior/source contract for the retired Master Relation Formula
// routes. Old bookmarks remain safe compatibility redirects; all formula
// mutation must pass through the canonical Production editor and its history.
defined('BASEPATH') || define('BASEPATH', __DIR__);

const MRCF_PAGE = 'production.component.formula.index';
const MRCF_FIELD = 'master_relation_component_formula_mutation_csrf';
const MRCF_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

final class MasterRelationFormulaRedirectResponse extends RuntimeException {}

final class MasterRelationFormulaRedirectInput
{
    public array $events = [];
    public function __construct(private string $method, private array $post = []) {}
    public function method($upper = false): string
    {
        $this->events[] = 'method';
        return $upper ? strtoupper($this->method) : strtolower($this->method);
    }
    public function post($key = null, $xssClean = false)
    {
        $this->events[] = 'post:' . (string)$key;
        return $this->post[(string)$key] ?? null;
    }
}

final class MasterRelationFormulaRedirectSession
{
    public array $flashes = [];
    public function __construct(private array $values = []) {}
    public function userdata($key) { return $this->values[(string)$key] ?? null; }
    public function set_flashdata($key, $value): void { $this->flashes[] = [(string)$key, (string)$value]; }
}

final class MasterRelationFormulaRedirectModel
{
    public array $calls = [];
    public function get_by_id($table, $id): array
    {
        $this->calls[] = [(string)$table, (int)$id];
        return (string)$table === 'mst_component_formula'
            ? ['id' => (int)$id, 'component_id' => 12]
            : [];
    }
}

class MY_Controller
{
    public MasterRelationFormulaRedirectInput $input;
    public MasterRelationFormulaRedirectSession $session;
    public MasterRelationFormulaRedirectModel $Master_model;
    public array $permissions = [];
    public bool $allowed = true;
    public function __construct() {}
    public function require_permission($page, $action): void
    {
        $this->permissions[] = [(string)$page, (string)$action];
        if (!$this->allowed) throw new MasterRelationFormulaRedirectResponse('Forbidden', 403);
    }
}

$redirects = [];
function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $redirects;
    $redirects[] = (string)$uri;
}
function show_error($message, $statusCode = 500, $heading = ''): void
{
    throw new MasterRelationFormulaRedirectResponse((string)$message, (int)$statusCode);
}
function show_404(): void { throw new MasterRelationFormulaRedirectResponse('Not Found', 404); }

require dirname(__DIR__, 2) . '/application/controllers/Master_relation.php';

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) $failures[] = $message;
};
$methodSource = static function (string $source, string $method): string {
    if (preg_match('/(?:public|private|protected) function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) return '';
    $start = (int)$match[0][1];
    if (preg_match('/\n    (?:public|private|protected) function\s+/', $source, $next, PREG_OFFSET_CAPTURE, $start + 1) !== 1) return substr($source, $start);
    return substr($source, $start, (int)$next[0][1] - $start);
};
$fixture = static function (string $httpMethod = 'GET', array $post = [], bool $allowed = true): Master_relation {
    $reflection = new ReflectionClass(Master_relation::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MasterRelationFormulaRedirectInput($httpMethod, $post);
    $controller->session = new MasterRelationFormulaRedirectSession([MRCF_FIELD => MRCF_TOKEN]);
    $controller->Master_model = new MasterRelationFormulaRedirectModel();
    $controller->allowed = $allowed;
    return $controller;
};
$invoke = static function (Master_relation $controller, string $method, int $id = 0): ?Throwable {
    try {
        if ($method === 'component_formula_hub') $controller->{$method}();
        else $controller->{$method}($id);
        return null;
    } catch (Throwable $error) {
        return $error;
    }
};

$root = dirname(__DIR__, 2);
$source = (string)file_get_contents($root . '/application/controllers/Master_relation.php');
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$listView = (string)file_get_contents($root . '/application/views/master/relation_list.php');
$formView = (string)file_get_contents($root . '/application/views/master/relation_form.php');

$check(
    strpos($routes, '$route[\'master/relation/component-formula/(:num)/store\']') !== false
        && strpos($routes, 'master_relation/component_formula_store/$1') !== false,
    'legacy store route remains as an explicit compatibility target'
);
$check(strpos($source, 'redirectLegacyComponentFormulaEditor') !== false, 'controller owns one fixed canonical editor redirect helper');

$readRoutes = [
    'component_formula_hub' => ['view', 0, 'production/component-formulas'],
    'component_formula' => ['view', 12, 'production/component-formulas/detail/12'],
    'component_formula_create' => ['create', 12, 'production/component-formulas/edit/12'],
    'component_formula_edit' => ['edit', 91, 'production/component-formulas/edit/12'],
];
foreach ($readRoutes as $method => [$action, $id, $target]) {
    $controller = $fixture();
    $redirects = [];
    $error = $invoke($controller, $method, $id);
    $check($error === null && $controller->permissions === [[MRCF_PAGE, $action]], $method . ' keeps canonical RBAC');
    $check($redirects === [$target], $method . ' redirects to the fixed canonical formula surface');
    $expectedReads = $method === 'component_formula_edit' ? [['mst_component_formula', 91]] : [];
    $check($controller->Master_model->calls === $expectedReads, $method . ' performs only the minimum compatibility read');

    $denied = $fixture('GET', [], false);
    $redirects = [];
    $deniedError = $invoke($denied, $method, $id);
    $check($deniedError instanceof MasterRelationFormulaRedirectResponse && $deniedError->getCode() === 403, $method . ' denies unauthorised access');
    $check($redirects === [] && $denied->Master_model->calls === [], $method . ' denial has no redirect or data read');
}

$writers = [
    'component_formula_store' => ['create', 12, []],
    'component_formula_update' => ['edit', 91, [['mst_component_formula', 91]]],
    'component_formula_delete' => ['delete', 91, [['mst_component_formula', 91]]],
];
foreach ($writers as $method => [$action, $id, $expectedReads]) {
    $sourceBlock = $methodSource($source, $method);
    $check(
        strpos($sourceBlock, "requireRelationPermission('formula', '" . $action . "')") !== false
            && strpos($sourceBlock, 'requireComponentFormulaMutationCsrf()') !== false
            && strpos($sourceBlock, 'redirectLegacyComponentFormulaEditor(') !== false,
        $method . ' keeps RBAC and scoped POST/CSRF before the canonical redirect'
    );
    $check(
        strpos($sourceBlock, "insert('mst_component_formula'") === false
            && strpos($sourceBlock, "update('mst_component_formula'") === false
            && strpos($sourceBlock, "delete('mst_component_formula'") === false,
        $method . ' contains no legacy formula DML'
    );

    $get = $fixture('GET');
    $redirects = [];
    $getError = $invoke($get, $method, $id);
    $check($getError instanceof MasterRelationFormulaRedirectResponse && $getError->getCode() === 405, $method . ' rejects GET');
    $check($redirects === [] && $get->Master_model->calls === [], $method . ' GET performs no compatibility read');

    $invalid = $fixture('POST', [MRCF_FIELD => str_repeat('b', 64)]);
    $redirects = [];
    $invalidError = $invoke($invalid, $method, $id);
    $check($invalidError instanceof MasterRelationFormulaRedirectResponse && $invalidError->getCode() === 403, $method . ' rejects an invalid legacy token');
    $check($redirects === [] && $invalid->Master_model->calls === [], $method . ' invalid token performs no compatibility read');

    $valid = $fixture('POST', [MRCF_FIELD => MRCF_TOKEN]);
    $redirects = [];
    $validError = $invoke($valid, $method, $id);
    $check($validError === null && $valid->permissions === [[MRCF_PAGE, $action]], $method . ' valid legacy request keeps canonical RBAC');
    $check($redirects === ['production/component-formulas/edit/12'], $method . ' valid legacy request enters canonical editor');
    $check($valid->Master_model->calls === $expectedReads, $method . ' valid request performs no write and only required row lookup');
    $check(count($valid->session->flashes) === 1 && $valid->session->flashes[0][0] === 'warning', $method . ' explains the compatibility redirect once');
}

$check(
    strpos($listView, "master/relation/component-formula/edit/") === false
        && strpos($listView, "master/relation/component-formula/delete/") === false
        && strpos($listView, "production/component-formulas/edit/") !== false,
    'legacy relation list no longer renders direct formula edit or delete writers'
);
$check(
    strpos($formView, "master/relation/component-formula/") === false
        && strpos($formView, "production/component-formulas/edit/") !== false,
    'legacy relation form no longer points its back action to legacy Formula routes'
);
$check(strpos($source, 'Pos_mobile') === false, 'legacy formula retirement does not alter POS Mobile/APK contracts');

if ($failures !== []) {
    fwrite(STDERR, 'Master relation formula canonical redirect smoke FAILED' . PHP_EOL);
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . PHP_EOL);
    fwrite(STDERR, 'Checks: ' . $checks . '; failures: ' . count($failures) . PHP_EOL);
    exit(1);
}
echo 'Master relation formula canonical redirect smoke passed (' . $checks . ' checks).' . PHP_EOL;
