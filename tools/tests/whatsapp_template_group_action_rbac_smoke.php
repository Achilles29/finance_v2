<?php

declare(strict_types=1);

/**
 * DB-free behavioral smoke test for WhatsApp template/group action RBAC.
 *
 * The real controller is instantiated without its constructor and receives
 * small in-memory CI fakes. Denied requests must stop before payload fields,
 * database work, session writes, uploads, or bot API dependencies are touched.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WHATSAPP_ACTION_RBAC_CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

final class WhatsappActionRbacDenied extends RuntimeException
{
}

final class WhatsappActionRbacInput
{
    public array $postReads = [];

    private string $requestMethod;
    private array $postData;

    public function __construct(string $requestMethod, array $postData)
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
    }

    public function method($upper = false): string
    {
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key, $xssClean = false)
    {
        $key = (string)$key;
        $this->postReads[] = $key;
        return $this->postData[$key] ?? null;
    }
}

final class WhatsappActionRbacDb
{
    public array $events = [];

    private string $table = '';

    public function from($table): self
    {
        $this->table = (string)$table;
        $this->events[] = 'query:from:' . $this->table;
        return $this;
    }

    public function where($key, $value = null): self
    {
        $this->events[] = 'query:where:' . (string)$key;
        return $this;
    }

    public function limit($limit, $offset = null): self
    {
        $this->events[] = 'query:limit';
        return $this;
    }

    public function get($table = ''): self
    {
        if ((string)$table !== '') {
            $this->table = (string)$table;
        }
        $this->events[] = 'query:get:' . $this->table;
        return $this;
    }

    public function order_by($key, $direction = ''): self
    {
        $this->events[] = 'query:order_by:' . (string)$key;
        return $this;
    }

    public function row_array(): array
    {
        $this->events[] = 'query:row:' . $this->table;
        return [
            'id' => 17,
            'is_active' => 1,
            'group_jid' => 'synthetic-group',
            'group_name' => 'Synthetic Group',
        ];
    }

    public function result_array(): array
    {
        $this->events[] = 'query:result:' . $this->table;
        return [];
    }

    public function insert($table, $data): bool
    {
        $this->events[] = 'write:insert:' . (string)$table;
        return true;
    }

    public function update($table, $data): bool
    {
        $this->events[] = 'write:update:' . (string)$table;
        return true;
    }

    public function delete($table): bool
    {
        $this->events[] = 'write:delete:' . (string)$table;
        return true;
    }
}

final class WhatsappActionRbacSession
{
    public array $writes = [];
    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        return $this->values[(string)$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $this->values[(string)$key] = $value;
        $this->writes[] = 'session:' . (string)$key;
    }

    public function set_flashdata($key, $value): void
    {
        $this->writes[] = 'session:' . (string)$key;
    }
}

final class WhatsappActionRbacOutput
{
    public ?int $status = null;

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        return $this;
    }

    public function set_content_type($contentType): self
    {
        return $this;
    }

    public function set_output($body): self
    {
        return $this;
    }
}

class MY_Controller
{
    public $input;
    public $db;
    public $session;
    public $output;
    public array $permissionCalls = [];
    public array $permissions = [];
    public array $forbiddenDependencies = [];
    public array $renderCalls = [];
    protected array $current_user = ['id' => 101];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->permissionCalls[] = [$page, $action];
        if (empty($this->permissions[$page][$action])) {
            throw new WhatsappActionRbacDenied('denied', 403);
        }
    }

    public function can($page, $action = 'view'): bool
    {
        return !empty($this->permissions[(string)$page][(string)$action]);
    }

    public function render($view, array $data = []): void
    {
        $this->renderCalls[] = (string)$view;
    }

    public function __get($name)
    {
        $this->forbiddenDependencies[] = (string)$name;
        throw new RuntimeException('unexpected dependency access: ' . (string)$name);
    }
}

$whatsappActionRbacRedirects = [];
function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $whatsappActionRbacRedirects;
    $whatsappActionRbacRedirects[] = (string)$uri;
}

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

$whatsappActionRbacChecks = 0;
$whatsappActionRbacFailures = [];

function whatsapp_action_rbac_check(bool $condition, string $message): void
{
    global $whatsappActionRbacChecks, $whatsappActionRbacFailures;
    $whatsappActionRbacChecks++;
    if (!$condition) {
        $whatsappActionRbacFailures[] = $message;
    }
}

/**
 * @return array{controller:Whatsapp,input:WhatsappActionRbacInput,db:WhatsappActionRbacDb,session:WhatsappActionRbacSession}
 */
function whatsapp_action_rbac_controller(
    array $postData,
    array $permissions,
    string $requestMethod = 'POST'
): array
{
    $input = new WhatsappActionRbacInput($requestMethod, $postData);
    $db = new WhatsappActionRbacDb();
    $session = new WhatsappActionRbacSession([
        'wa_template_group_mutation_csrf' => WHATSAPP_ACTION_RBAC_CSRF,
    ]);
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->db = $db;
    $controller->session = $session;
    $controller->output = new WhatsappActionRbacOutput();
    $controller->permissions = $permissions;

    return compact('controller', 'input', 'db', 'session');
}

function whatsapp_action_rbac_payload(string $area, string $action, int $id): array
{
    if ($area === 'template') {
        return [
            'action' => $action,
            'id' => $id,
            'wa_template_group_mutation_csrf' => WHATSAPP_ACTION_RBAC_CSRF,
            'template_code' => 'TPL-SMOKE',
            'name' => 'Template Smoke',
            'category' => 'INFO',
            'body' => 'Synthetic body',
            'sample_variables' => '{}',
        ];
    }

    return [
        'action' => $action,
        'id' => $id,
        'wa_template_group_mutation_csrf' => WHATSAPP_ACTION_RBAC_CSRF,
        'group_key' => 'GROUP-SMOKE',
        'group_name' => 'Group Smoke',
        'group_jid' => 'synthetic-group',
        'purpose' => 'Smoke',
        'notes' => 'Synthetic notes',
        'message' => 'Synthetic message',
    ];
}

$mutationCases = [
    ['template', 'save', 0, 'create', 'write:insert:wa_template'],
    ['template', 'save', 17, 'edit', 'write:update:wa_template'],
    ['template', 'toggle', 17, 'edit', 'write:update:wa_template'],
    ['template', 'delete', 17, 'delete', 'write:delete:wa_template'],
    ['group', 'save', 0, 'create', 'write:insert:wa_group_map'],
    ['group', 'save', 17, 'edit', 'write:update:wa_group_map'],
    ['group', 'toggle', 17, 'edit', 'write:update:wa_group_map'],
    ['group', 'delete', 17, 'delete', 'write:delete:wa_group_map'],
];

foreach ($mutationCases as [$area, $action, $id, $requiredAction, $expectedWriter]) {
    $page = 'wa.' . $area;
    $label = $area . ' ' . $action . ($action === 'save' ? ($id > 0 ? ' edit' : ' create') : '');
    $fixture = whatsapp_action_rbac_controller(
        whatsapp_action_rbac_payload($area, $action, $id),
        [$page => ['view' => true]]
    );
    $denial = null;
    try {
        $fixture['controller']->{$area}();
    } catch (WhatsappActionRbacDenied $exception) {
        $denial = $exception;
    } catch (Throwable $exception) {
        whatsapp_action_rbac_check(false, $label . ' denial reached an unexpected dependency');
    }

    whatsapp_action_rbac_check(
        $denial instanceof WhatsappActionRbacDenied && $denial->getCode() === 403,
        $label . ' returns 403 for a view-only role'
    );
    whatsapp_action_rbac_check(
        $fixture['controller']->permissionCalls === [[$page, 'view'], [$page, $requiredAction]],
        $label . ' checks the correct writer permission'
    );
    whatsapp_action_rbac_check(
        $fixture['input']->postReads === ['action', 'id'],
        $label . ' reads only action and id before denial'
    );
    whatsapp_action_rbac_check(
        $fixture['db']->events === [],
        $label . ' performs no query or database write before denial'
    );
    whatsapp_action_rbac_check(
        $fixture['session']->writes === [] && $fixture['controller']->forbiddenDependencies === [],
        $label . ' performs no session write, upload, or bot dependency access before denial'
    );

    $allowed = whatsapp_action_rbac_controller(
        whatsapp_action_rbac_payload($area, $action, $id),
        [$page => ['view' => true, $requiredAction => true]]
    );
    try {
        $allowed['controller']->{$area}();
    } catch (Throwable $exception) {
        whatsapp_action_rbac_check(false, $label . ' valid permission path completed without dependency errors');
    }
    $writeEvents = array_values(array_filter(
        $allowed['db']->events,
        static fn(string $event): bool => strpos($event, 'write:') === 0
    ));
    whatsapp_action_rbac_check(
        $writeEvents === [$expectedWriter],
        $label . ' valid permission reaches only ' . $expectedWriter
    );
    whatsapp_action_rbac_check(
        $allowed['controller']->permissionCalls === [[$page, 'view'], [$page, $requiredAction]],
        $label . ' valid path retains the correct permission mapping'
    );
}

foreach (['template', 'group'] as $area) {
    $page = 'wa.' . $area;
    $get = whatsapp_action_rbac_controller([], [$page => ['view' => true]], 'GET');
    try {
        $get['controller']->{$area}();
    } catch (Throwable $exception) {
        whatsapp_action_rbac_check(false, $area . ' GET view-only path completed without dependency errors');
    }
    whatsapp_action_rbac_check(
        $get['controller']->permissionCalls === [[$page, 'view']]
            && $get['controller']->renderCalls === ['wa/' . $area],
        $area . ' GET remains available with view permission'
    );
    whatsapp_action_rbac_check(
        array_filter($get['db']->events, static fn(string $event): bool => strpos($event, 'write:') === 0) === [],
        $area . ' GET performs no database write'
    );
}

// send_group is an existing create action. Its denial must remain ahead of
// id/message reads, upload handling, the group query, and the bot API call.
$sendGroup = whatsapp_action_rbac_controller(
    whatsapp_action_rbac_payload('group', 'send_group', 17),
    ['wa.group' => ['view' => true]]
);
$sendGroupDenial = null;
try {
    $sendGroup['controller']->group();
} catch (WhatsappActionRbacDenied $exception) {
    $sendGroupDenial = $exception;
} catch (Throwable $exception) {
    whatsapp_action_rbac_check(false, 'group send_group denial reached an unexpected dependency');
}
whatsapp_action_rbac_check(
    $sendGroupDenial instanceof WhatsappActionRbacDenied && $sendGroupDenial->getCode() === 403,
    'group send_group returns 403 for a view-only role'
);
whatsapp_action_rbac_check(
    $sendGroup['controller']->permissionCalls === [['wa.group', 'view'], ['wa.group', 'create']],
    'group send_group retains the wa.group:create permission'
);
whatsapp_action_rbac_check(
    $sendGroup['input']->postReads === ['action']
        && $sendGroup['db']->events === []
        && $sendGroup['session']->writes === []
        && $sendGroup['controller']->forbiddenDependencies === [],
    'group send_group denial precedes payload, query, write, upload, and bot API dependencies'
);

if ($whatsappActionRbacFailures !== []) {
    foreach ($whatsappActionRbacFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(
        STDERR,
        '[FAIL] WhatsApp template/group action RBAC smoke: '
        . count($whatsappActionRbacFailures) . ' failure(s), '
        . $whatsappActionRbacChecks . ' checks.' . PHP_EOL
    );
    exit(1);
}

echo '[PASS] WhatsApp template/group action RBAC smoke: '
    . $whatsappActionRbacChecks
    . ' checks; 8 CRUD mutation paths plus send_group:create verified without DB/network/bootstrap.'
    . PHP_EOL;
