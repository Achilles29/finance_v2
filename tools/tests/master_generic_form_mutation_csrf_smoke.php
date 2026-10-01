<?php

// DB-free source/helper smoke for the generic Master create/edit form boundary.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

final class MasterMutationRejected extends RuntimeException
{
    public function __construct(public int $status, string $message)
    {
        parent::__construct($message);
    }
}

function show_error($message = '', $statusCode = 500, $heading = ''): void
{
    throw new MasterMutationRejected((int)$statusCode, (string)$message);
}

class MY_Controller
{
    public $input;
    public $output;
    public $session;
}

final class MasterMutationInput
{
    public int $methodReads = 0;
    public int $fieldReads = 0;
    public int $headerReads = 0;

    public function __construct(
        private string $method,
        private array $fields = [],
        private array $headers = []
    ) {
    }

    public function method(bool $upper = false): string
    {
        $this->methodReads++;
        return $upper ? strtoupper($this->method) : strtolower($this->method);
    }

    public function post($key = null, bool $clean = false)
    {
        $this->fieldReads++;
        return $this->fields[$key] ?? null;
    }

    public function get_request_header(string $name, bool $clean = false): string
    {
        $this->headerReads++;
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string)$key, $name) === 0) {
                return (string)$value;
            }
        }
        return '';
    }
}

final class MasterMutationOutput
{
    public array $headers = [];

    public function set_header(string $header): self
    {
        $this->headers[] = $header;
        return $this;
    }
}

final class MasterMutationSession
{
    public array $reads = [];
    public array $writes = [];

    public function __construct(private array $values = [])
    {
    }

    public function userdata(string $key)
    {
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata(string $key, $value): void
    {
        $this->writes[$key] = $value;
        $this->values[$key] = $value;
    }

    public function value(string $key)
    {
        return $this->values[$key] ?? null;
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Master.php';

$checks = 0;
$failures = [];

function mm_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function mm_method(string $source, string $method): string
{
    $pattern = '/\n    (?:public|private|protected) function ' . preg_quote($method, '/') . '\s*\(/';
    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }
    $start = (int)$match[0][1];
    if (preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + strlen($match[0][0])) !== 1) {
        return substr($source, $start);
    }
    return substr($source, $start, (int)$next[0][1] - $start);
}

function mm_ordered(string $source, array $needles): bool
{
    $position = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $position) {
            return false;
        }
        $position = $next;
    }
    return true;
}

function mm_controller(string $method, array $fields = [], array $headers = [], array $session = []): array
{
    $controller = (new ReflectionClass(Master::class))->newInstanceWithoutConstructor();
    $controller->input = new MasterMutationInput($method, $fields, $headers);
    $controller->output = new MasterMutationOutput();
    $controller->session = new MasterMutationSession($session);
    return [$controller, $controller->input, $controller->output, $controller->session];
}

function mm_guard(Master $controller): bool
{
    $method = new ReflectionMethod(Master::class, 'requireMasterMutationRequest');
    $method->setAccessible(true);
    return $method->invoke($controller);
}

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Master.php');
$view = file_get_contents($root . '/application/views/master/form.php');
$reflection = new ReflectionClass(Master::class);

$expectedConstants = [
    'MASTER_MUTATION_CSRF_SESSION_KEY' => 'master_mutation_csrf',
    'MASTER_MUTATION_CSRF_FORM_FIELD' => 'master_mutation_csrf',
    'MASTER_MUTATION_CSRF_HEADER' => 'X-Master-Mutation-CSRF',
    'MASTER_MUTATION_CSRF_CI_HEADER' => 'X-Master-Mutation-Csrf',
];
foreach ($expectedConstants as $name => $value) {
    $constant = $reflection->getReflectionConstant($name);
    mm_check($constant !== false && $constant->getValue() === $value, $name . ' is scoped to the Master domain');
}

$helper = mm_method($source, 'requireMasterMutationRequest');
mm_check(
    strpos($helper, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($helper, 'MASTER_MUTATION_CSRF_FORM_FIELD') < strpos($helper, 'MASTER_MUTATION_CSRF_CI_HEADER'),
    'helper uses hash_equals and accepts the multipart field before the dedicated CI header'
);

foreach (['create' => 'create', 'edit' => 'edit'] as $action => $permission) {
    $block = mm_method($source, $action);
    mm_check(
        mm_ordered($block, ["redirect_contract_operational_if_needed", 'entityConfig(', "requireMasterPermission(\$entity, '" . $permission . "')", 'masterMutationCsrf()'])
            && strpos($block, "'master_mutation_csrf_token' => \$masterMutationCsrfToken") !== false,
        $action . ' generates and passes the token only after config and RBAC'
    );
}

$store = mm_method($source, 'store');
$update = mm_method($source, 'update');
mm_check(
    mm_ordered($store, ['redirect_contract_operational_if_needed', 'entityConfig(', 'requireMasterPermission(', 'requireMasterMutationRequest()', 'autofillCodeFromName(', 'applyValidation(', 'collectPayload(', 'handleProductPhotoUpload(', 'Master_model->insert(']),
    'store guards POST/token before validation, payload, upload, and insert'
);
mm_check(
    mm_ordered($update, ['redirect_contract_operational_if_needed', 'entityConfig(', 'requireMasterPermission(', 'requireMasterMutationRequest()', 'Master_model->get_by_id(', 'autofillCodeFromName(', 'applyValidation(', 'collectPayload(', 'handleProductPhotoUpload(', 'Master_model->update(']),
    'update guards POST/token before row lookup, validation, payload, upload, and update'
);
mm_check(
    strpos($source, "if (\$entity === 'hr-contract-template')") !== false
        && strpos($source, "redirect('hr-contracts/templates')") !== false
        && strpos($source, "if (\$entity === 'hr-contract')") !== false
        && strpos($source, "redirect('hr/contracts')") !== false
        && substr_count($store, "redirect('master/' . \$entity . '/create')") >= 3
        && substr_count($update, "redirect('master/' . \$entity . '/edit/' . \$id)") >= 3,
    'legacy contract and validation/upload redirect behavior remains present'
);
mm_check(
    strpos($view, "enctype=\"multipart/form-data\"") !== false
        && substr_count($view, 'name="master_mutation_csrf"') === 1
        && strpos($view, "html_escape((string)(\$master_mutation_csrf_token ?? ''))") !== false,
    'generic form emits one escaped hidden token and retains multipart support'
);

$token = str_repeat('a', 64);
foreach (['GET', 'PUT'] as $verb) {
    [$controller, $input, $output, $session] = mm_controller($verb, ['master_mutation_csrf' => $token], [], ['master_mutation_csrf' => $token]);
    $writers = 0;
    try {
        if (mm_guard($controller)) {
            $writers++;
        }
        mm_check(false, $verb . ' must be rejected');
    } catch (MasterMutationRejected $rejected) {
        mm_check(
            $rejected->status === 405 && $writers === 0 && $input->fieldReads === 0
                && $input->headerReads === 0 && $session->reads === []
                && in_array('Allow: POST', $output->headers, true),
            $verb . ' receives 405 before token or writer access'
        );
    }
}

foreach (['missing' => '', 'wrong' => str_repeat('b', 64)] as $label => $provided) {
    [$controller, $input] = mm_controller('POST', ['master_mutation_csrf' => $provided], [], ['master_mutation_csrf' => $token]);
    $writers = 0;
    try {
        if (mm_guard($controller)) {
            $writers++;
        }
        mm_check(false, $label . ' token must be rejected');
    } catch (MasterMutationRejected $rejected) {
        mm_check($rejected->status === 403 && $writers === 0 && strpos($rejected->getMessage(), $token) === false, $label . ' token receives generic 403 before writer');
    }
}

[$controller] = mm_controller('POST', ['master_mutation_csrf' => $token], [], ['master_mutation_csrf' => $token]);
$writers = 0;
if (mm_guard($controller)) {
    $writers++;
}
mm_check($writers === 1, 'valid multipart token reaches the simulated writer exactly once');

[$controller] = mm_controller('POST', [], ['X-Master-Mutation-CSRF' => $token], ['master_mutation_csrf' => $token]);
mm_check(mm_guard($controller) === true, 'dedicated HTTP header is accepted through CI-normalized lookup');

[$controller, $input, $output, $session] = mm_controller('GET', [], [], ['master_mutation_csrf' => 'invalid']);
$generator = new ReflectionMethod(Master::class, 'masterMutationCsrf');
$generator->setAccessible(true);
$generated = $generator->invoke($controller);
mm_check(
    preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1
        && $session->value('master_mutation_csrf') === $generated,
    'generator replaces invalid state with a stored random 64-hex token'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Master generic mutation CSRF smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' Master generic mutation CSRF smoke checks passed.' . PHP_EOL;
