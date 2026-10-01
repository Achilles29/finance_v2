<?php

// DB-free source/helper smoke for generic Master inline mutations.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

final class MasterInlineRejected extends RuntimeException
{
    public function __construct(public int $status, string $message)
    {
        parent::__construct($message);
    }
}

function show_error($message = '', $statusCode = 500, $heading = ''): void
{
    throw new MasterInlineRejected((int)$statusCode, (string)$message);
}

class MY_Controller
{
    public $input;
    public $output;
    public $session;
}

final class MasterInlineInput
{
    public int $fieldReads = 0;
    public int $headerReads = 0;

    public function __construct(
        private string $method,
        private bool $ajax,
        private array $fields = [],
        private array $headers = []
    ) {
    }

    public function method(bool $upper = false): string
    {
        return $upper ? strtoupper($this->method) : strtolower($this->method);
    }

    public function is_ajax_request(): bool
    {
        return $this->ajax;
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

final class MasterInlineOutput
{
    public int $status = 200;
    public array $headers = [];
    public string $body = '';

    public function set_header(string $header): self
    {
        $this->headers[] = $header;
        return $this;
    }

    public function set_status_header(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function set_content_type(string $type): self
    {
        return $this;
    }

    public function set_output(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}

final class MasterInlineSession
{
    public array $reads = [];

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
        $this->values[$key] = $value;
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Master.php';

$checks = 0;
$failures = [];

function mi_check(bool $condition, string $message): void
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

function mi_method(string $source, string $method): string
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

function mi_ordered(string $source, array $needles): bool
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

function mi_controller(string $method, bool $ajax, array $fields, array $headers, array $session): array
{
    $controller = (new ReflectionClass(Master::class))->newInstanceWithoutConstructor();
    $controller->input = new MasterInlineInput($method, $ajax, $fields, $headers);
    $controller->output = new MasterInlineOutput();
    $controller->session = new MasterInlineSession($session);
    return [$controller, $controller->input, $controller->output, $controller->session];
}

function mi_guard(Master $controller): bool
{
    $method = new ReflectionMethod(Master::class, 'requireMasterMutationRequest');
    $method->setAccessible(true);
    return $method->invoke($controller);
}

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Master.php');
$indexView = file_get_contents($root . '/application/views/master/index.php');
$materialView = file_get_contents($root . '/application/views/master/material_index.php');

$index = mi_method($source, 'index');
mi_check(
    mi_ordered($index, ['entityConfig(', "requireMasterPermission(\$entity, 'view')", 'masterMutationCsrf()'])
        && strpos($index, "'master_mutation_csrf_token' => \$masterMutationCsrfToken") !== false
        && strpos($index, "render('master/material_index', \$data)") !== false
        && strpos($index, "render('master/index', \$data)") !== false,
    'index generates one scoped token after RBAC and passes it to generic/material views'
);

$toggle = mi_method($source, 'toggle');
mi_check(
    mi_ordered($toggle, ['entityConfig(', 'requireMasterPermission(', 'requireMasterMutationRequest()', 'Master_model->get_by_id(', 'Master_model->toggle_active(']),
    'toggle guards POST/token before row lookup and write'
);
mi_check(
    strpos($toggle, 'is_ajax_request()') !== false
        && strpos($toggle, "set_flashdata('success', 'Status berhasil diubah.')") !== false
        && strpos($toggle, "redirect('master/' . \$entity)") !== false,
    'toggle retains AJAX result and legacy flash redirect markers'
);

$stockMode = mi_method($source, 'stock_mode');
mi_check(
    mi_ordered($stockMode, ['entityConfig(', 'requireMasterPermission(', 'requireMasterMutationRequest()', "field_exists('stock_mode'", 'Master_model->get_by_id(', 'Master_model->update(']),
    'stock_mode guards POST/token before schema check, row lookup, and write'
);
mi_check(
    strpos($stockMode, "\$cycle = ['MANUAL_AVAILABLE', 'MANUAL_OUT', 'AUTO']") !== false
        && strpos($stockMode, "set_flashdata('success', 'Mode stok berhasil diubah ke '") !== false,
    'stock_mode retains its mode cycle and success behavior'
);

$reorder = mi_method($source, 'reorder');
mi_check(
    mi_ordered($reorder, ['entityConfig(', 'requireMasterPermission(', 'requireMasterMutationRequest()', 'is_ajax_request()', "field_exists('sort_order'", "post('ids')", 'raw_input_stream', "select('id, sort_order')", 'beginMasterAuditTransaction()', "update((string)\$cfg['table']", 'writeMasterAudit(', 'finishMasterAuditTransaction(']),
    'reorder guards POST/token before field check, JSON IDs, query, audited transaction, and writes'
);
mi_check(
    strpos($reorder, 'Entity tidak mendukung drag & drop urutan.') !== false
        && strpos($reorder, 'Minimal dua baris diperlukan untuk ubah urutan.') !== false
        && strpos($reorder, "json_encode(['ok' => true])") !== false,
    'reorder retains unsupported, invalid payload, and success behavior'
);

mi_check(
    strpos($indexView, 'data-master-mutation-csrf="<?php echo html_escape($masterMutationCsrfToken); ?>"') !== false
        && substr_count($indexView, "'X-Master-Mutation-CSRF': masterMutationCsrf") === 2,
    'generic root emits the token and both postRowAction/saveOrder send the header'
);
mi_check(
    strpos($indexView, '<form method="post" action="<?php echo site_url(\'master/\' . $entity . \'/toggle/\'') !== false
        && strpos($indexView, 'name="master_mutation_csrf" value="<?php echo html_escape($masterMutationCsrfToken); ?>"') !== false
        && strpos($indexView, '<a class="btn btn-sm btn-outline-warning action-icon-btn"') === false,
    'non-product generic toggle is a confirmed POST form with hidden token'
);
mi_check(
    strpos($materialView, '<form method="post" action="<?php echo site_url(\'master/material/toggle/\'') !== false
        && strpos($materialView, 'name="master_mutation_csrf" value="<?php echo html_escape($masterMutationCsrfToken); ?>"') !== false
        && strpos($materialView, '<a href="<?php echo site_url(\'master/material/toggle/\'') === false,
    'material toggle is a confirmed POST form with hidden token'
);

$token = str_repeat('a', 64);
foreach (['GET', 'PUT'] as $verb) {
    [$controller, $input, $output, $session] = mi_controller($verb, true, ['master_mutation_csrf' => $token], [], ['master_mutation_csrf' => $token]);
    $writers = 0;
    if (mi_guard($controller)) {
        $writers++;
    }
    mi_check(
        $output->status === 405 && $writers === 0 && $input->fieldReads === 0
            && $input->headerReads === 0 && $session->reads === []
            && in_array('Allow: POST', $output->headers, true),
        $verb . ' AJAX request receives JSON 405 before token or writer access'
    );
}

[$controller, $input, $output] = mi_controller('POST', true, [], ['X-Master-Mutation-CSRF' => str_repeat('b', 64)], ['master_mutation_csrf' => $token]);
$writers = 0;
if (mi_guard($controller)) {
    $writers++;
}
$rejection = json_decode($output->body, true);
mi_check(
    $output->status === 403 && $writers === 0 && ($rejection['ok'] ?? true) === false
        && strpos($output->body, $token) === false,
    'invalid AJAX token receives generic JSON 403 before simulated downstream writer'
);

[$controller] = mi_controller('POST', true, [], ['X-Master-Mutation-CSRF' => $token], ['master_mutation_csrf' => $token]);
$writers = 0;
if (mi_guard($controller)) {
    $writers++;
}
mi_check($writers === 1, 'valid inline header reaches simulated writer exactly once');

[$controller] = mi_controller('POST', false, ['master_mutation_csrf' => str_repeat('c', 64)], [], ['master_mutation_csrf' => $token]);
try {
    mi_guard($controller);
    mi_check(false, 'invalid legacy form token must be rejected');
} catch (MasterInlineRejected $rejected) {
    mi_check($rejected->status === 403 && strpos($rejected->getMessage(), $token) === false, 'legacy form rejection remains a generic standard 403');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Master inline mutation CSRF smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' Master inline mutation CSRF smoke checks passed.' . PHP_EOL;
