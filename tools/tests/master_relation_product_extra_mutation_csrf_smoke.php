<?php

declare(strict_types=1);

/**
 * Behavior-level, DB/network/bootstrap-free smoke for the legacy product-extra
 * mapping mutation boundary in Master_relation.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const MRPE_PAGE = 'master.product_extra_map.index';
const MRPE_FIELD = 'master_relation_product_extra_mutation_csrf';
const MRPE_RECIPE_FIELD = 'master_relation_product_recipe_mutation_csrf';
const MRPE_FORMULA_FIELD = 'master_relation_component_formula_mutation_csrf';
const MRPE_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const MRPE_WRONG = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const MRPE_RECIPE_TOKEN = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const MRPE_FORMULA_TOKEN = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

final class MasterRelationProductExtraSmokeResponse extends RuntimeException
{
}

final class MasterRelationProductExtraSmokeInput
{
    public array $events = [];
    public array $postReads = [];
    public array $getReads = [];
    public array $headerReads = [];
    public int $rawReads = 0;

    private string $requestMethod;
    private array $postData;
    private array $getData;
    private array $headers;
    private string $rawBody;

    public function __construct(
        string $requestMethod,
        array $postData = [],
        array $getData = [],
        array $headers = [],
        string $rawBody = ''
    ) {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
        $this->getData = $getData;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
    }

    public function method($upper = false): string
    {
        $this->events[] = 'method';
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $key = $key === null ? '*' : (string)$key;
        $this->postReads[] = $key;
        $this->events[] = 'post:' . $key;
        return $key === '*' ? $this->postData : ($this->postData[$key] ?? null);
    }

    public function get($key = null, $xssClean = false)
    {
        $key = $key === null ? '*' : (string)$key;
        $this->getReads[] = $key;
        return $key === '*' ? $this->getData : ($this->getData[$key] ?? null);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->headerReads[] = $key;
        return (string)($this->headers[$key] ?? '');
    }

    public function __get($name)
    {
        if ((string)$name !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$name);
        }
        $this->rawReads++;
        return $this->rawBody;
    }
}

final class MasterRelationProductExtraSmokeSession
{
    public array $values;
    public array $reads = [];
    public array $writes = [];
    public array $flashes = [];

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        $key = (string)$key;
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value = null): void
    {
        $key = (string)$key;
        $this->writes[] = [$key, $value];
        $this->values[$key] = $value;
    }

    public function set_flashdata($key, $value): void
    {
        $this->flashes[] = [(string)$key, (string)$value];
    }
}

final class MasterRelationProductExtraSmokeResult
{
    private array $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function row_array(): array
    {
        return $this->rows[0] ?? [];
    }

    public function result_array(): array
    {
        return $this->rows;
    }
}

final class MasterRelationProductExtraSmokeStatement
{
    public array $calls = [];
    public int $errno = 0;
    public string $error = '';
    public int $affected_rows = 1;
    public bool $bindResult = true;
    public bool $executeResult = true;
    public bool $bindResultResult = true;
    public ?Throwable $bindThrowable = null;
    public ?Throwable $executeThrowable = null;
    public ?Throwable $bindResultThrowable = null;
    public ?Throwable $fetchThrowable = null;
    public $db;
    public string $operation;
    public string $sql;
    private array $resultReferences = [];

    public function __construct(string $operation, string $sql)
    {
        $this->operation = $operation;
        $this->sql = $sql;
    }

    public function bind_param($types, &...$values): bool
    {
        $this->calls[] = array_merge(['bind_param', (string)$types], array_values($values));
        if ($this->bindThrowable !== null) {
            throw $this->bindThrowable;
        }
        return $this->bindResult;
    }

    public function execute(): bool
    {
        $this->calls[] = ['execute'];
        if ($this->db instanceof MasterRelationProductExtraSmokeDb) {
            $eventMap = [
                'mapping_lookup' => 'lookup:mapping',
                'parent_lock' => 'lock:extra_group',
                'delete_parent_lock' => 'lock:extra_group',
                'product_read' => 'query:product',
                'duplicate_read' => 'query:duplicate',
                'mapping_lock' => 'lock:mapping',
                'insert' => 'insert:execute',
                'delete' => 'delete:mst_product_extra_map',
            ];
            $this->db->events[] = $eventMap[$this->operation] ?? ('prepared:' . $this->operation);
        }
        if ($this->executeThrowable !== null) {
            throw $this->executeThrowable;
        }
        return $this->executeResult;
    }

    public function bind_result(&...$values): bool
    {
        $this->calls[] = ['bind_result', count($values)];
        if ($this->bindResultThrowable !== null) {
            throw $this->bindResultThrowable;
        }
        $this->resultReferences = [];
        foreach ($values as $index => &$value) {
            $this->resultReferences[$index] = &$value;
        }
        unset($value);
        return $this->bindResultResult;
    }

    public function fetch()
    {
        $this->calls[] = ['fetch'];
        if ($this->fetchThrowable !== null) {
            throw $this->fetchThrowable;
        }
        if (!$this->db instanceof MasterRelationProductExtraSmokeDb) {
            return false;
        }
        $behavior = $this->db->preparedBehavior[$this->operation] ?? [];
        if (array_key_exists('fetch_result', $behavior) && $behavior['fetch_result'] === false) {
            return false;
        }
        $row = $this->db->preparedRow($this->operation);
        if (!$row) {
            return null;
        }
        foreach (array_values($row) as $index => $value) {
            if (array_key_exists($index, $this->resultReferences)) {
                $this->resultReferences[$index] = $value;
            }
        }
        return true;
    }

    public function close(): void
    {
        $this->calls[] = ['close'];
    }
}

final class MasterRelationProductExtraSmokeConnection
{
    public array $calls = [];
    public array $statements = [];
    public int $errno = 0;
    public string $error = '';
    public ?MasterRelationProductExtraSmokeStatement $statement = null;
    public $db;

    private function operationForSql(string $sql): string
    {
        if (strpos($sql, 'INSERT INTO mst_product_extra_map') === 0) {
            return 'insert';
        }
        if (strpos($sql, 'DELETE FROM mst_product_extra_map') === 0) {
            return 'delete';
        }
        if (strpos($sql, 'FROM mst_extra_group') !== false) {
            return strpos($sql, 'product_division_id') !== false ? 'parent_lock' : 'delete_parent_lock';
        }
        if (strpos($sql, 'FROM mst_product WHERE') !== false) {
            return 'product_read';
        }
        if (strpos($sql, 'product_id = ? AND extra_group_id = ? LIMIT 1') !== false) {
            return 'duplicate_read';
        }
        if (strpos($sql, 'FROM mst_product_extra_map') !== false && strpos($sql, 'FOR UPDATE') !== false) {
            return 'mapping_lock';
        }
        if (strpos($sql, 'FROM mst_product_extra_map') !== false) {
            return 'mapping_lookup';
        }
        return 'unknown';
    }

    public function prepare($sql)
    {
        $sql = (string)$sql;
        $operation = $this->operationForSql($sql);
        $this->calls[] = [
            'prepare',
            $sql,
            'operation' => $operation,
            'db_debug' => $this->db instanceof MasterRelationProductExtraSmokeDb ? $this->db->db_debug : null,
        ];
        $behavior = $this->db instanceof MasterRelationProductExtraSmokeDb
            ? ($this->db->preparedBehavior[$operation] ?? [])
            : [];
        $this->errno = (int)($behavior['connection_errno'] ?? 0);
        $this->error = (string)($behavior['connection_error'] ?? '');
        if (($behavior['prepare_throwable'] ?? null) instanceof Throwable) {
            throw $behavior['prepare_throwable'];
        }
        if (array_key_exists('prepare_result', $behavior) && !$behavior['prepare_result']) {
            return false;
        }

        $statement = new MasterRelationProductExtraSmokeStatement($operation, $sql);
        $statement->db = $this->db;
        $statement->errno = (int)($behavior['statement_errno'] ?? 0);
        $statement->error = (string)($behavior['statement_error'] ?? '');
        $statement->affected_rows = (int)($behavior['affected_rows'] ?? 1);
        $statement->bindResult = (bool)($behavior['bind_result'] ?? true);
        $statement->executeResult = (bool)($behavior['execute_result'] ?? true);
        $statement->bindResultResult = (bool)($behavior['bind_result_result'] ?? true);
        $statement->bindThrowable = $behavior['bind_throwable'] ?? null;
        $statement->executeThrowable = $behavior['execute_throwable'] ?? null;
        $statement->bindResultThrowable = $behavior['bind_result_throwable'] ?? null;
        $statement->fetchThrowable = $behavior['fetch_throwable'] ?? null;
        $this->statements[$operation][] = $statement;
        $this->statement = $statement;
        return $statement;
    }
}

final class MasterRelationProductExtraSmokeDb
{
    public array $calls = [];
    public array $events = [];
    public array $getRows = [];
    public array $getWhereRows = [];
    public array $modelFixtures = [];
    public array $preparedBehavior = [];
    public bool $db_debug = true;
    public string $dbdriver = 'mysqli';
    public $conn_id;
    public bool $transBeginResult = true;
    public bool $transStatusResult = true;
    public bool $transCommitResult = true;
    public bool $deleteResult = true;
    public int $affectedRows = 1;
    public int $queryFailAt = 0;
    public int $queryCount = 0;
    public ?array $mappingLockRows = null;

    public function __construct()
    {
        $this->conn_id = new MasterRelationProductExtraSmokeConnection();
        $this->conn_id->db = $this;
    }

    public function preparedRow(string $operation): array
    {
        $behavior = $this->preparedBehavior[$operation] ?? [];
        if (array_key_exists('rows', $behavior)) {
            return $behavior['rows'][0] ?? [];
        }
        if ($operation === 'parent_lock' || $operation === 'delete_parent_lock') {
            $id = 7;
            if (array_key_exists('mst_extra_group', $this->modelFixtures)) {
                $row = $this->modelFixtures['mst_extra_group'][$id] ?? [];
            } else {
                $row = ['id' => $id, 'product_division_id' => null, 'is_active' => 1];
            }
            if (!$row) {
                return [];
            }
            return $operation === 'parent_lock'
                ? [(int)($row['id'] ?? 0), $row['product_division_id'] ?? null, (int)($row['is_active'] ?? 0)]
                : [(int)($row['id'] ?? 0)];
        }
        if ($operation === 'product_read') {
            $row = array_key_exists('mst_product', $this->modelFixtures)
                ? ($this->modelFixtures['mst_product'][12] ?? [])
                : ['id' => 12, 'product_division_id' => 4, 'is_active' => 1];
            return $row ? [(int)($row['id'] ?? 0), $row['product_division_id'] ?? null, (int)($row['is_active'] ?? 0)] : [];
        }
        if ($operation === 'duplicate_read') {
            return $this->getWhereRows ? [(int)($this->getWhereRows[0]['id'] ?? 0)] : [];
        }
        if ($operation === 'mapping_lookup' || $operation === 'mapping_lock') {
            if ($operation === 'mapping_lock' && $this->mappingLockRows !== null) {
                $row = $this->mappingLockRows[0] ?? [];
            } else {
                $row = array_key_exists('mst_product_extra_map', $this->modelFixtures)
                    ? ($this->modelFixtures['mst_product_extra_map'][91] ?? [])
                    : ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7];
            }
            if (!$row) {
                return [];
            }
            return $operation === 'mapping_lookup'
                ? [(int)($row['product_id'] ?? 0), (int)($row['extra_group_id'] ?? 0)]
                : [(int)($row['id'] ?? 0), (int)($row['product_id'] ?? 0), (int)($row['extra_group_id'] ?? 0)];
        }
        return [];
    }

    public function insert($table, array $payload): bool
    {
        $this->calls[] = ['insert', [(string)$table, $payload], 'db_debug' => $this->db_debug];
        if ((string)$table === 'aud_transaction_log') {
            return true;
        }
        log_message('error', 'Query error: SECRET_SQL_ERROR_MARKER Invalid query: INSERT LEAK MARKER');
        return false;
    }

    public function table_exists($table): bool
    {
        $this->calls[] = ['table_exists', [(string)$table]];
        return (string)$table === 'aud_transaction_log';
    }

    public function field_exists($field, $table): bool
    {
        $this->calls[] = ['field_exists', [(string)$field, (string)$table]];
        return (string)$table === 'aud_transaction_log' && in_array((string)$field, [
            'module_code', 'action_code', 'entity_table', 'entity_id',
            'actor_user_id', 'source_ip', 'before_payload', 'after_payload',
            'notes', 'created_at',
        ], true);
    }

    public function trans_begin(): bool
    {
        $this->calls[] = ['trans_begin', []];
        $this->events[] = 'trans_begin';
        return $this->transBeginResult;
    }

    public function trans_status(): bool
    {
        $this->calls[] = ['trans_status', []];
        $this->events[] = 'trans_status';
        return $this->transStatusResult;
    }

    public function trans_commit(): bool
    {
        $this->calls[] = ['trans_commit', []];
        $this->events[] = 'trans_commit';
        return $this->transCommitResult;
    }

    public function trans_rollback(): bool
    {
        $this->calls[] = ['trans_rollback', []];
        $this->events[] = 'trans_rollback';
        return true;
    }

    public function query($sql, array $binds = [])
    {
        $sql = (string)$sql;
        $this->calls[] = ['query', [$sql, $binds]];
        $this->queryCount++;
        if ($this->queryFailAt === $this->queryCount) {
            $this->events[] = 'query:failed';
            return false;
        }

        if (strpos($sql, 'FROM mst_extra_group') !== false) {
            $this->events[] = 'lock:extra_group';
            $id = (int)($binds[0] ?? 0);
            if (array_key_exists('mst_extra_group', $this->modelFixtures)) {
                $row = $this->modelFixtures['mst_extra_group'][$id] ?? [];
            } else {
                $row = [
                    'id' => $id,
                    'product_division_id' => null,
                    'is_active' => 1,
                ];
            }
            return new MasterRelationProductExtraSmokeResult($row ? [$row] : []);
        }

        if (strpos($sql, 'FROM mst_product_extra_map') !== false) {
            $this->events[] = 'lock:mapping';
            if ($this->mappingLockRows !== null) {
                return new MasterRelationProductExtraSmokeResult($this->mappingLockRows);
            }
            $id = (int)($binds[0] ?? 0);
            if (array_key_exists('mst_product_extra_map', $this->modelFixtures)) {
                $row = $this->modelFixtures['mst_product_extra_map'][$id] ?? [];
            } else {
                $row = ['id' => $id, 'product_id' => 12, 'extra_group_id' => (int)($binds[1] ?? 7)];
            }
            return new MasterRelationProductExtraSmokeResult($row ? [$row] : []);
        }

        if (strpos($sql, 'FROM mst_product') !== false) {
            $this->events[] = 'query:product';
            $id = (int)($binds[0] ?? 0);
            if (array_key_exists('mst_product', $this->modelFixtures)) {
                $row = $this->modelFixtures['mst_product'][$id] ?? [];
            } else {
                $row = ['id' => $id, 'product_division_id' => 4, 'is_active' => 1];
            }
            return new MasterRelationProductExtraSmokeResult($row ? [$row] : []);
        }

        return new MasterRelationProductExtraSmokeResult($this->getRows);
    }

    public function delete($table): bool
    {
        $this->calls[] = ['delete', [(string)$table]];
        $this->events[] = 'delete:' . (string)$table;
        return $this->deleteResult;
    }

    public function affected_rows(): int
    {
        $this->calls[] = ['affected_rows', []];
        $this->events[] = 'affected_rows';
        return $this->affectedRows;
    }

    public function __call($name, $arguments)
    {
        $name = (string)$name;
        $this->calls[] = [$name, $arguments];
        if ($name === 'get') {
            return new MasterRelationProductExtraSmokeResult($this->getRows);
        }
        if ($name === 'get_where') {
            $table = (string)($arguments[0] ?? '');
            $this->events[] = 'get_where:' . $table;
            return new MasterRelationProductExtraSmokeResult($this->getWhereRows);
        }
        return $this;
    }
}

final class MasterRelationProductExtraSmokeModel
{
    public array $calls = [];
    public array $fixtures = [];

    public function get_by_id($table, $id): array
    {
        $table = (string)$table;
        $id = (int)$id;
        $this->calls[] = ['get_by_id', $table, $id];
        if (array_key_exists($table, $this->fixtures)) {
            return $this->fixtures[$table][$id] ?? [];
        }
        if ($table === 'mst_product_extra_map') {
            return ['id' => $id, 'product_id' => 12, 'extra_group_id' => 7, 'sort_order' => 0];
        }
        if ($table === 'mst_product') {
            return [
                'id' => $id,
                'product_code' => 'FIXTURE',
                'product_name' => 'Fixture Product',
                'product_division_id' => 4,
                'is_active' => 1,
            ];
        }
        if ($table === 'mst_extra_group') {
            return [
                'id' => $id,
                'group_name' => 'Fixture Extra Group',
                'product_division_id' => null,
                'is_active' => 1,
            ];
        }
        return [];
    }

    public function get_options($table, $valueField, $labelField, $activeOnly = false): array
    {
        $this->calls[] = ['get_options', (string)$table];
        return [['value' => '7', 'label' => 'Fixture Extra Group']];
    }

    public function insert($table, array $payload): int
    {
        $this->calls[] = ['insert', (string)$table, $payload];
        return 1;
    }
}

class MY_Controller
{
    public MasterRelationProductExtraSmokeInput $input;
    public MasterRelationProductExtraSmokeSession $session;
    public MasterRelationProductExtraSmokeDb $db;
    public MasterRelationProductExtraSmokeModel $Master_model;
    public array $permissionCalls = [];
    public bool $permissionAllowed = true;
    public ?string $renderedView = null;
    public array $renderedData = [];
    public array $redirects = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new MasterRelationProductExtraSmokeResponse('Forbidden', 403);
        }
    }

    protected function render(string $view, array $data = [], bool $return = false)
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
        return null;
    }
}

function show_error($message, $statusCode = 500, $heading = ''): void
{
    throw new MasterRelationProductExtraSmokeResponse((string)$message, (int)$statusCode);
}

function show_404(): void
{
    throw new MasterRelationProductExtraSmokeResponse('Not Found', 404);
}

function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $mrpeRedirects;
    $mrpeRedirects[] = (string)$uri;
}

function site_url($uri = ''): string
{
    return '/smoke/' . ltrim((string)$uri, '/');
}

function html_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function set_value($field, $default = '')
{
    return $default;
}

function log_message($level, $message): void
{
    global $mrpeLogs;
    $mrpeLogs[] = [(string)$level, (string)$message];
}

require dirname(__DIR__, 2) . '/application/controllers/Master_relation.php';

final class MasterRelationProductExtraSmokeViewLoader
{
    public function view($view, array $data = []): void
    {
    }
}

final class MasterRelationProductExtraSmokeViewHost
{
    public MasterRelationProductExtraSmokeViewLoader $load;

    public function __construct()
    {
        $this->load = new MasterRelationProductExtraSmokeViewLoader();
    }

    public function render(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string)ob_get_clean();
    }
}

$checks = 0;
$failures = [];

function mrpe_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function mrpe_method_source(string $source, string $method): string
{
    if (preg_match('/(?:public|private|protected) function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }
    $start = (int)$match[0][1];
    if (preg_match('/\n    (?:public|private|protected) function\s+/', $source, $next, PREG_OFFSET_CAPTURE, $start + 1) !== 1) {
        return substr($source, $start);
    }
    return substr($source, $start, (int)$next[0][1] - $start);
}

function mrpe_fixture(
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRPE_FIELD => MRPE_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = '',
    array $modelFixtures = [],
    array $dbGetWhereRows = [],
    array $dbGetRows = [],
    array $dbBehavior = []
): array {
    global $mrpeRedirects, $mrpeLogs;
    $mrpeRedirects = [];
    $mrpeLogs = [];
    $reflection = new ReflectionClass(Master_relation::class);
    /** @var Master_relation&MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MasterRelationProductExtraSmokeInput($method, $post, $get, $headers, $rawBody);
    $controller->session = new MasterRelationProductExtraSmokeSession($sessionValues);
    $controller->db = new MasterRelationProductExtraSmokeDb();
    $controller->Master_model = new MasterRelationProductExtraSmokeModel();
    $controller->Master_model->fixtures = $modelFixtures;
    $controller->db->modelFixtures = $modelFixtures;
    $controller->db->getWhereRows = $dbGetWhereRows;
    $controller->db->getRows = $dbGetRows;
    $controller->db->db_debug = (bool)($dbBehavior['db_debug'] ?? true);
    $controller->db->dbdriver = (string)($dbBehavior['dbdriver'] ?? 'mysqli');
    $controller->db->transBeginResult = (bool)($dbBehavior['trans_begin_result'] ?? true);
    $controller->db->transStatusResult = (bool)($dbBehavior['trans_status_result'] ?? true);
    $controller->db->transCommitResult = (bool)($dbBehavior['trans_commit_result'] ?? true);
    $controller->db->deleteResult = (bool)($dbBehavior['delete_result'] ?? true);
    $controller->db->affectedRows = (int)($dbBehavior['affected_rows'] ?? 1);
    $controller->db->queryFailAt = (int)($dbBehavior['query_fail_at'] ?? 0);
    $controller->db->mappingLockRows = array_key_exists('mapping_lock_rows', $dbBehavior)
        ? $dbBehavior['mapping_lock_rows']
        : null;
    $controller->db->preparedBehavior = (array)($dbBehavior['prepared'] ?? []);
    $legacyInsertKeys = [
        'connection_errno', 'connection_error', 'prepare_result', 'prepare_throwable',
        'statement_errno', 'statement_error', 'bind_result', 'execute_result',
        'bind_throwable', 'execute_throwable',
    ];
    foreach ($legacyInsertKeys as $legacyInsertKey) {
        if (array_key_exists($legacyInsertKey, $dbBehavior)) {
            $controller->db->preparedBehavior['insert'][$legacyInsertKey] = $dbBehavior[$legacyInsertKey];
        }
    }
    if (array_key_exists('affected_rows', $dbBehavior)) {
        $controller->db->preparedBehavior['delete']['affected_rows'] = (int)$dbBehavior['affected_rows'];
    }
    if (array_key_exists('delete_result', $dbBehavior)) {
        $controller->db->preparedBehavior['delete']['execute_result'] = (bool)$dbBehavior['delete_result'];
    }
    if (!empty($dbBehavior['unsupported_connection'])) {
        $controller->db->conn_id = new stdClass();
    }
    $controller->permissionAllowed = $permissionAllowed;
    return [$controller, $reflection];
}

function mrpe_invoke_writer(
    string $writer,
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRPE_FIELD => MRPE_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = '',
    array $modelFixtures = [],
    array $dbGetWhereRows = [],
    array $dbGetRows = [],
    array $dbBehavior = []
): array {
    [$controller] = mrpe_fixture(
        $method,
        $post,
        $permissionAllowed,
        $sessionValues,
        $get,
        $headers,
        $rawBody,
        $modelFixtures,
        $dbGetWhereRows,
        $dbGetRows,
        $dbBehavior
    );
    $response = null;
    try {
        $controller->{$writer}($writer === 'product_extra_store' ? 12 : 91);
    } catch (MasterRelationProductExtraSmokeResponse $exception) {
        $response = $exception;
    }
    global $mrpeRedirects;
    $controller->redirects = $mrpeRedirects;
    return [$controller, $response];
}

function mrpe_has_call(array $calls, string $method, string $table): bool
{
    foreach ($calls as $call) {
        $target = $call[1] ?? '';
        if (is_array($target)) {
            $target = $target[0] ?? '';
        }
        if (($call[0] ?? '') === $method && $target === $table) {
            return true;
        }
    }
    return false;
}

function mrpe_event_before(array $events, string $first, string $second): bool
{
    $firstAt = array_search($first, $events, true);
    $secondAt = array_search($second, $events, true);
    return $firstAt !== false && $secondAt !== false && $firstAt < $secondAt;
}

function mrpe_prepared_statement(MY_Controller $controller, string $operation)
{
    if (!$controller->db->conn_id instanceof MasterRelationProductExtraSmokeConnection) {
        return null;
    }
    return $controller->db->conn_id->statements[$operation][0] ?? null;
}

function mrpe_prepared_statement_count(MY_Controller $controller): int
{
    if (!$controller->db->conn_id instanceof MasterRelationProductExtraSmokeConnection) {
        return 0;
    }
    return array_sum(array_map('count', $controller->db->conn_id->statements));
}

function mrpe_rejected_store_has_no_write(MY_Controller $controller, string $label): void
{
    mrpe_check($controller->redirects === ['master/relation/product-extra/12/create'], $label . ' redirects back to create');
    mrpe_check(count($controller->session->flashes) === 1 && $controller->session->flashes[0][0] === 'error', $label . ' sets one error flash');
    mrpe_check(!mrpe_has_call($controller->db->calls, 'get_where', 'mst_product_extra_map'), $label . ' stops before duplicate lookup');
    mrpe_check(
        !mrpe_has_call($controller->db->calls, 'insert', 'mst_product_extra_map')
            && !mrpe_has_call($controller->Master_model->calls, 'insert', 'mst_product_extra_map'),
        $label . ' guarantees no mapping insert'
    );
}

$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/application/controllers/Master_relation.php');
$formViewPath = $root . '/application/views/master/relation_form.php';
$listViewPath = $root . '/application/views/master/relation_list.php';
$formViewSource = (string)file_get_contents($formViewPath);
$listViewSource = (string)file_get_contents($listViewPath);
$writers = [
    'product_extra_store' => 'create',
    'product_extra_delete' => 'delete',
];

mrpe_check(
    strpos($controllerSource, "PRODUCT_EXTRA_MUTATION_CSRF_SESSION_KEY = '" . MRPE_FIELD . "'") !== false
        && strpos($controllerSource, "PRODUCT_EXTRA_MUTATION_CSRF_FORM_FIELD = '" . MRPE_FIELD . "'") !== false,
    'controller declares the exact isolated product-extra session key and form field'
);

$tokenSource = mrpe_method_source($controllerSource, 'productExtraMutationCsrf');
$guardSource = mrpe_method_source($controllerSource, 'requireProductExtraMutationCsrf');
mrpe_check(
    strpos($tokenSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'token helper generates and validates strict lowercase 64-hex tokens'
);
mrpe_check(
    strpos($guardSource, "method(true) !== 'POST'") < strpos($guardSource, '->post(')
        && strpos($guardSource, '->post(') < strpos($guardSource, 'hash_equals(')
        && strpos($guardSource, ', 405,') !== false
        && strpos($guardSource, ', 403,') !== false,
    'guard orders POST-only before scoped form token and constant-time comparison'
);
mrpe_check(
    strpos($guardSource, 'get_request_header') === false
        && strpos($guardSource, '->get(') === false
        && strpos($guardSource, 'raw_input_stream') === false
        && strpos($guardSource, 'json_decode') === false
        && strpos($guardSource, MRPE_RECIPE_FIELD) === false
        && strpos($guardSource, MRPE_FORMULA_FIELD) === false,
    'guard contains no query, header, raw JSON, or alternate-field fallback'
);

$createSource = mrpe_method_source($controllerSource, 'product_extra_create');
mrpe_check(
    strpos($createSource, "get_by_id('mst_product', \$productId)") !== false
        && strpos($createSource, "get_by_id('mst_product', \$productId)") < strpos($createSource, "from('mst_extra_group')"),
    'product_extra_create requires the target product before loading options'
);
mrpe_check(
    strpos($createSource, "from('mst_extra_group')") !== false
        && strpos($createSource, "where('is_active', 1)") !== false
        && strpos($createSource, "where('product_division_id IS NULL', null, false)") !== false
        && strpos($createSource, "or_where('product_division_id', \$productDivisionId)") !== false
        && strpos($createSource, "order_by('sort_order', 'ASC')") !== false,
    'product_extra_create query limits options to active generic or matching-division groups'
);
mrpe_check(
    strpos($createSource, "get_options('mst_extra_group'") === false
        && strpos($createSource, 'source_kind') === false,
    'product_extra_create does not rely on broad options or add source-kind policy'
);

$storeSource = mrpe_method_source($controllerSource, 'product_extra_store');
$auditReadySource = mrpe_method_source($controllerSource, 'productExtraAuditReady');
$auditWriteSource = mrpe_method_source($controllerSource, 'writeProductExtraAudit');
$storePayloadAt = strpos($storeSource, "->post('extra_group_id'");
$storeBeginAt = strpos($storeSource, 'trans_begin()');
$storeGroupAt = strpos($storeSource, 'SELECT id, product_division_id, is_active FROM mst_extra_group');
$storeProductAt = strpos($storeSource, 'SELECT id, product_division_id, is_active FROM mst_product');
$storeDuplicateAt = strpos($storeSource, 'SELECT id FROM mst_product_extra_map WHERE product_id = ? AND extra_group_id = ? LIMIT 1');
$storeInsertAt = strpos($storeSource, "'INSERT INTO mst_product_extra_map");
mrpe_check(
    $storePayloadAt !== false && $storeBeginAt !== false && $storeGroupAt !== false && $storeProductAt !== false
        && $storeDuplicateAt !== false && $storeInsertAt !== false
        && $storePayloadAt < $storeBeginAt && $storeBeginAt < $storeGroupAt
        && $storeGroupAt < $storeProductAt && $storeProductAt < $storeDuplicateAt
        && $storeDuplicateAt < $storeInsertAt,
    'product_extra_store parses input, begins, locks/revalidates group, and revalidates product before duplicate lookup and insert'
);
mrpe_check(
    strpos($storeSource, "preg_match('/\\A[1-9][0-9]*\\z/D'") !== false
        && strpos($storeSource, 'FILTER_VALIDATE_INT') !== false
        && strpos($storeSource, '(int)$this->input->post(\'extra_group_id\'') === false,
    'product_extra_store parses only canonical positive IDs without blind input casting'
);
mrpe_check(
    strpos($storeSource, "['is_active']") !== false
        && strpos($storeSource, "['product_division_id']") !== false
        && strpos($storeSource, 'source_kind') === false,
    'product_extra_store enforces only active product/group and exact group-division policy'
);
mrpe_check(
    strpos($storeSource, "Master_model->insert('mst_product_extra_map'") === false
        && strpos($storeSource, "db->insert('mst_product_extra_map'") === false
        && strpos($storeSource, 'productExtraPreparedSelectOne(') !== false
        && strpos($storeSource, 'productExtraPreparedMutation(') !== false
        && strpos($storeSource, "'INSERT INTO mst_product_extra_map (product_id, extra_group_id, sort_order) VALUES (?, ?, ?)'" ) !== false
        && strpos($storeSource, 'db_debug = false') !== false
        && strpos($storeSource, '$exception->') === false
        && strpos($storeSource, 'finally') !== false
        && stripos($storeSource, 'insert ignore') === false
        && stripos($storeSource, 'on duplicate key') === false,
    'product_extra_store uses mysqli prepared integer binds, numeric errno only, and restores db_debug through finally'
);
mrpe_check(
    strpos($storeSource, 'expectedRevision') === false
        && strpos($storeSource, 'canonicalExtraGroupProductMappingRevision') === false,
    'legacy product_extra_store does not claim or emulate the B67 checklist revision protocol'
);
mrpe_check(
    strpos($auditReadySource, "table_exists('aud_transaction_log')") !== false
        && strpos($auditReadySource, "'before_payload'") !== false
        && strpos($auditReadySource, "'after_payload'") !== false,
    'product-extra writer fails closed when its audit schema is unavailable'
);
mrpe_check(
    strpos($auditWriteSource, "'CREATE_PRODUCT_EXTRA_MAP'") === false
        && strpos($auditWriteSource, "'before_payload'") !== false
        && strpos($auditWriteSource, "'after_payload'") !== false,
    'product-extra audit helper accepts caller-specific actions and writes before/after payloads'
);
mrpe_check(
    $storeInsertAt < strpos($storeSource, 'writeProductExtraAudit(')
        && strpos($storeSource, 'writeProductExtraAudit(') < strpos($storeSource, 'trans_commit()'),
    'store writes its audit record after the prepared insert and before commit'
);

$deleteSource = mrpe_method_source($controllerSource, 'product_extra_delete');
$deleteBeginAt = strpos($deleteSource, 'trans_begin()');
$deleteLookupAt = strpos($deleteSource, 'SELECT product_id, extra_group_id FROM mst_product_extra_map WHERE id = ? LIMIT 1');
$deleteParentLockAt = strpos($deleteSource, 'SELECT id FROM mst_extra_group WHERE id = ? FOR UPDATE');
$deleteMappingLockAt = strpos($deleteSource, 'SELECT id, product_id, extra_group_id FROM mst_product_extra_map WHERE id = ? AND extra_group_id = ? FOR UPDATE');
$deleteDmlAt = strpos($deleteSource, 'DELETE FROM mst_product_extra_map WHERE id = ? AND extra_group_id = ?');
mrpe_check(
    $deleteLookupAt !== false && $deleteBeginAt !== false && $deleteParentLockAt !== false && $deleteMappingLockAt !== false && $deleteDmlAt !== false
        && $deleteLookupAt < $deleteBeginAt && $deleteBeginAt < $deleteParentLockAt && $deleteParentLockAt < $deleteMappingLockAt
        && $deleteMappingLockAt < $deleteDmlAt,
    'product_extra_delete reads mapping directly, begins, then locks parent before exact mapping and delete'
);
mrpe_check(
    strpos($deleteSource, "['affected_rows']") !== false
        && strpos($deleteSource, '$affectedRows !== 1') !== false,
    'product_extra_delete constrains prepared exact-row DML and verifies exactly one affected row'
);
mrpe_check(
    $deleteDmlAt < strpos($deleteSource, 'writeProductExtraAudit(')
        && strpos($deleteSource, 'writeProductExtraAudit(') < strpos($deleteSource, 'trans_commit()'),
    'delete writes its audit record after exact-row DML and before commit'
);
mrpe_check(
    strpos($storeSource, '$this->db->query(') === false
        && strpos($storeSource, '->get_where(') === false
        && strpos($storeSource, '->get_by_id(') === false
        && strpos($storeSource, "->delete('mst_product_extra_map')") === false
        && strpos($deleteSource, '$this->db->query(') === false
        && strpos($deleteSource, '->get_where(') === false
        && strpos($deleteSource, '->get_by_id(') === false
        && strpos($deleteSource, "->delete('mst_product_extra_map')") === false,
    'both B68 writer bodies contain no CI query/get/model lookup/query-builder delete path'
);

$preparedSelectSource = mrpe_method_source($controllerSource, 'productExtraPreparedSelectOne');
$preparedMutationSource = mrpe_method_source($controllerSource, 'productExtraPreparedMutation');
mrpe_check(
    strpos($preparedSelectSource, "dbdriver ?? '') !== 'mysqli'") !== false
        && strpos($preparedSelectSource, '->prepare($sql)') !== false
        && strpos($preparedSelectSource, "str_repeat('i'") !== false
        && strpos($preparedSelectSource, "'bind_result'") !== false
        && strpos($preparedSelectSource, '->fetch()') !== false
        && strpos($preparedSelectSource, 'get_result') === false
        && strpos($preparedMutationSource, '->prepare($sql)') !== false
        && strpos($preparedMutationSource, "str_repeat('i'") !== false
        && strpos($preparedMutationSource, "'affected_rows' =>") !== false,
    'shared B68 helpers enforce direct mysqli integer binds and portable bind_result/fetch handling'
);

foreach ($writers as $writer => $action) {
    $source = mrpe_method_source($controllerSource, $writer);
    $permissionAt = strpos($source, "requireRelationPermission('extra', '" . $action . "')");
    $guardAt = strpos($source, 'requireProductExtraMutationCsrf()');
    $businessMarkers = [
        'Master_model->get_by_id(',
        "->post('extra_group_id'",
        "->post('sort_order'",
        'Master_model->insert(',
        'productExtraPreparedSelectOne(',
        'productExtraPreparedMutation(',
        "->delete('mst_product_extra_map'",
        "->get_where('mst_product_extra_map'",
    ];
    $firstBusinessAt = strlen($source);
    foreach ($businessMarkers as $marker) {
        $position = strpos($source, $marker);
        if ($position !== false) {
            $firstBusinessAt = min($firstBusinessAt, $position);
        }
    }
    mrpe_check(
        $source !== '' && $permissionAt !== false && $guardAt !== false
            && $permissionAt < $guardAt && $guardAt < $firstBusinessAt,
        $writer . ' orders canonical RBAC, POST/token guard, then parent/payload/model/DB access'
    );

    foreach (['GET', 'PUT'] as $nonPostMethod) {
        [$methodController, $methodResponse] = mrpe_invoke_writer($writer, $nonPostMethod);
        mrpe_check($methodResponse instanceof MasterRelationProductExtraSmokeResponse && $methodResponse->getCode() === 405, $writer . ' rejects allowed ' . $nonPostMethod . ' with HTTP 405');
        mrpe_check($methodController->permissionCalls === [[MRPE_PAGE, $action]], $writer . ' ' . $nonPostMethod . ' uses exact canonical RBAC');
        mrpe_check($methodController->input->postReads === [] && $methodController->session->reads === [], $writer . ' ' . $nonPostMethod . ' reads no token or business input');
        mrpe_check($methodController->db->calls === [] && $methodController->Master_model->calls === [], $writer . ' ' . $nonPostMethod . ' performs no parent/row/model/DB work');
    }

    [$deniedController, $deniedResponse] = mrpe_invoke_writer(
        $writer,
        'POST',
        [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => 7],
        false
    );
    mrpe_check($deniedResponse instanceof MasterRelationProductExtraSmokeResponse && $deniedResponse->getCode() === 403, $writer . ' is blocked by RBAC');
    mrpe_check($deniedController->permissionCalls === [[MRPE_PAGE, $action]], $writer . ' denial uses exact canonical RBAC');
    mrpe_check($deniedController->input->events === [] && $deniedController->session->reads === [], $writer . ' RBAC denial precedes method/token/input reads');
    mrpe_check($deniedController->db->calls === [] && $deniedController->Master_model->calls === [], $writer . ' RBAC denial performs no parent/row/model/DB work');

    $rejections = [
        'missing' => [[], [], [], ''],
        'malformed' => [[MRPE_FIELD => 'NOT-lowercase-64-hex'], [], [], ''],
        'wrong' => [[MRPE_FIELD => MRPE_WRONG], [], [], ''],
        'cross-scope recipe token' => [[MRPE_FIELD => MRPE_RECIPE_TOKEN], [], [], ''],
        'query fallback' => [[], [MRPE_FIELD => MRPE_TOKEN], [], ''],
        'header fallback' => [[], [], [MRPE_FIELD => MRPE_TOKEN], ''],
        'raw JSON fallback' => [[], [], [], json_encode([MRPE_FIELD => MRPE_TOKEN])],
        'recipe field fallback' => [[MRPE_RECIPE_FIELD => MRPE_TOKEN], [], [], ''],
        'component field fallback' => [[MRPE_FORMULA_FIELD => MRPE_TOKEN], [], [], ''],
    ];
    foreach ($rejections as $label => [$post, $get, $headers, $raw]) {
        $post['extra_group_id'] = 7;
        $post['sort_order'] = 3;
        [$invalidController, $invalidResponse] = mrpe_invoke_writer(
            $writer,
            'POST',
            $post,
            true,
            [
                MRPE_FIELD => MRPE_TOKEN,
                MRPE_RECIPE_FIELD => MRPE_RECIPE_TOKEN,
                MRPE_FORMULA_FIELD => MRPE_FORMULA_TOKEN,
            ],
            $get,
            $headers,
            (string)$raw
        );
        mrpe_check($invalidResponse instanceof MasterRelationProductExtraSmokeResponse && $invalidResponse->getCode() === 403, $writer . ' rejects ' . $label . ' with HTTP 403');
        mrpe_check($invalidController->input->postReads === [MRPE_FIELD], $writer . ' ' . $label . ' reads only the scoped form field');
        mrpe_check($invalidController->input->getReads === [] && $invalidController->input->headerReads === [] && $invalidController->input->rawReads === 0, $writer . ' ' . $label . ' ignores non-form channels');
        mrpe_check($invalidController->db->calls === [] && $invalidController->Master_model->calls === [], $writer . ' ' . $label . ' performs no parent/row/input/model/DB work');
    }
}

[$guardController, $guardReflection] = mrpe_fixture('POST', [MRPE_FIELD => MRPE_TOKEN]);
$guard = $guardReflection->getMethod('requireProductExtraMutationCsrf');
$guard->setAccessible(true);
mrpe_check($guard->invoke($guardController) === true, 'valid scoped form token is accepted');
mrpe_check(
    $guardController->input->postReads === [MRPE_FIELD]
        && $guardController->session->reads === [MRPE_FIELD],
    'valid guard reads exactly the product-extra form and session scope'
);

$activeProduct = [
    'id' => 12,
    'product_code' => 'FIXTURE',
    'product_name' => 'Fixture Product',
    'product_division_id' => 4,
    'is_active' => 1,
];
$inactiveProduct = array_replace($activeProduct, ['is_active' => 0]);
$genericGroup = [
    'id' => 7,
    'group_name' => 'Generic Group',
    'product_division_id' => null,
    'is_active' => 1,
];
$matchingGroup = array_replace($genericGroup, ['group_name' => 'Division Group', 'product_division_id' => 4]);
$inactiveGroup = array_replace($genericGroup, ['is_active' => 0]);
$mismatchedGroup = array_replace($genericGroup, ['product_division_id' => 9]);

[$optionsController] = mrpe_fixture(
    'GET',
    [],
    true,
    [],
    [],
    [],
    '',
    ['mst_product' => [12 => $activeProduct]],
    [],
    [
        ['value' => '7', 'label' => 'Generic Group'],
        ['value' => '8', 'label' => 'Division Group'],
    ]
);
$optionsController->product_extra_create(12);
mrpe_check(
    ($optionsController->renderedData['options']['extra_groups'] ?? []) === [
        ['value' => '7', 'label' => 'Generic Group'],
        ['value' => '8', 'label' => 'Division Group'],
    ],
    'product_extra_create renders the explicitly filtered option query result'
);
mrpe_check(
    mrpe_has_call($optionsController->db->calls, 'from', 'mst_extra_group')
        && in_array(['where', ['is_active', 1]], $optionsController->db->calls, true)
        && in_array(['where', ['product_division_id IS NULL', null, false]], $optionsController->db->calls, true)
        && in_array(['or_where', ['product_division_id', 4]], $optionsController->db->calls, true),
    'product_extra_create behavior applies active, generic, and target-division predicates'
);
mrpe_check(
    !mrpe_has_call($optionsController->Master_model->calls, 'get_options', 'mst_extra_group'),
    'product_extra_create behavior does not call the broad legacy options helper'
);

[$missingCreateController] = mrpe_fixture(
    'GET',
    [],
    true,
    [],
    [],
    [],
    '',
    ['mst_product' => []]
);
$missingCreateResponse = null;
try {
    $missingCreateController->product_extra_create(12);
} catch (MasterRelationProductExtraSmokeResponse $exception) {
    $missingCreateResponse = $exception;
}
mrpe_check(
    $missingCreateResponse instanceof MasterRelationProductExtraSmokeResponse
        && $missingCreateResponse->getCode() === 404,
    'product_extra_create preserves missing-product HTTP 404 behavior'
);
mrpe_check(
    $missingCreateController->db->calls === []
        && $missingCreateController->session->reads === []
        && $missingCreateController->renderedView === null,
    'missing create parent stops before options, token, and render work'
);

$validStorePost = [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => 7, 'sort_order' => 3];
[$storeController, $storeResponse] = mrpe_invoke_writer('product_extra_store', 'POST', $validStorePost);
mrpe_check($storeResponse === null, 'product_extra_store valid form request completes');
mrpe_check($storeController->permissionCalls === [[MRPE_PAGE, 'create']], 'product_extra_store valid request preserves create RBAC');
mrpe_check(
    array_column($storeController->db->conn_id->calls, 'operation') === [
        'parent_lock', 'product_read', 'duplicate_read', 'insert',
    ]
        && count(array_filter($storeController->db->conn_id->calls, static function (array $call): bool {
            return ($call['db_debug'] ?? null) === false;
        })) === 4,
    'successful store uses four ordered direct mysqli prepares inside the db_debug=false boundary'
);
mrpe_check(
    mrpe_prepared_statement($storeController, 'insert')->calls === [
        ['bind_param', 'iii', 12, 7, 3],
        ['execute'],
        ['close'],
    ],
    'successful prepared insert binds only three typed integers, executes, and closes'
);
mrpe_check(
    mrpe_prepared_statement($storeController, 'parent_lock')->calls === [
        ['bind_param', 'i', 7], ['execute'], ['bind_result', 3], ['fetch'], ['close'],
    ]
        && mrpe_prepared_statement($storeController, 'product_read')->calls === [
            ['bind_param', 'i', 12], ['execute'], ['bind_result', 3], ['fetch'], ['close'],
        ]
        && mrpe_prepared_statement($storeController, 'duplicate_read')->calls === [
            ['bind_param', 'ii', 12, 7], ['execute'], ['bind_result', 1], ['fetch'], ['close'],
        ],
    'store parent/product/duplicate reads use prepared integer binds and portable result handling'
);
mrpe_check(
    !mrpe_has_call($storeController->db->calls, 'insert', 'mst_product_extra_map')
        && !mrpe_has_call($storeController->Master_model->calls, 'insert', 'mst_product_extra_map'),
    'successful store bypasses framework and Master_model insert paths'
);
mrpe_check(
    mrpe_has_call($storeController->db->calls, 'insert', 'aud_transaction_log'),
    'successful store writes its audit row in the existing transaction'
);
mrpe_check($storeController->db->db_debug === true, 'successful insert restores the prior db_debug value');
mrpe_check(
    count($storeController->session->flashes) === 1
        && $storeController->session->flashes[0] === ['success', 'Mapping product-extra berhasil ditambahkan.'],
    'successful direct insert retains the legacy success flash only'
);
mrpe_check(in_array('extra_group_id', $storeController->input->postReads, true) && in_array('sort_order', $storeController->input->postReads, true), 'product_extra_store reads business input only after valid guard');
mrpe_check($storeController->redirects === ['master/relation/product-extra/12'], 'valid generic mapping keeps the legacy success redirect');
mrpe_check(
    mrpe_event_before($storeController->db->events, 'trans_begin', 'lock:extra_group')
        && mrpe_event_before($storeController->db->events, 'lock:extra_group', 'query:product')
        && mrpe_event_before($storeController->db->events, 'query:product', 'query:duplicate')
        && mrpe_event_before($storeController->db->events, 'query:duplicate', 'insert:execute')
        && mrpe_event_before($storeController->db->events, 'insert:execute', 'trans_commit'),
    'successful store serializes on the parent before in-transaction product revalidation, duplicate check, insert, and commit'
);
mrpe_check(
    $storeController->Master_model->calls === []
        && in_array('trans_status', $storeController->db->events, true)
        && end($storeController->db->events) === 'trans_commit'
        && $mrpeLogs === [],
    'successful store performs current entity reads inside the checked transaction and commits only after healthy status'
);

[$storeBeginFailure, $storeBeginFailureResponse] = mrpe_invoke_writer(
    'product_extra_store',
    'POST',
    $validStorePost,
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [],
    [],
    [],
    ['trans_begin_result' => false, 'db_debug' => false]
);
mrpe_check($storeBeginFailureResponse === null, 'store begin failure stays in the generic legacy response flow');
mrpe_check(
    $storeBeginFailure->db->events === ['trans_begin']
        && $storeBeginFailure->db->conn_id->calls === [],
    'store begin failure performs no lock, duplicate check, prepared insert, or DML'
);
mrpe_check(
    $storeBeginFailure->session->flashes === [['error', 'Mapping product-extra gagal ditambahkan. Silakan coba lagi.']]
        && $storeBeginFailure->redirects === ['master/relation/product-extra/12/create']
        && $storeBeginFailure->db->db_debug === false,
    'store begin failure returns only a generic error, no false success, and restores db_debug'
);
mrpe_check(
    $mrpeLogs === [[
        'error',
        'Master_relation::product_extra_store product_id=12 extra_group_id=7 transaction_begin_failed',
    ]],
    'store begin failure logs only safe identifiers and failure stage'
);

foreach (['parent_lock' => 'parent lock', 'product_read' => 'product revalidation'] as $operation => $label) {
    [$storeQueryFailure, $storeQueryFailureResponse] = mrpe_invoke_writer(
        'product_extra_store',
        'POST',
        $validStorePost,
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        [],
        [],
        [],
        ['prepared' => [$operation => [
            'execute_result' => false,
            'statement_errno' => 1205,
            'statement_error' => 'SECRET_' . strtoupper($operation) . '_SQL_DETAIL',
        ]]]
    );
    mrpe_check($storeQueryFailureResponse === null, 'store ' . $label . ' query failure is contained');
    mrpe_check(
        in_array('trans_rollback', $storeQueryFailure->db->events, true)
            && !in_array('insert:execute', $storeQueryFailure->db->events, true)
            && !in_array('trans_commit', $storeQueryFailure->db->events, true),
        'store ' . $label . ' query failure rolls back before insert and commit'
    );
    mrpe_check(
        $storeQueryFailure->session->flashes === [['error', 'Mapping product-extra gagal ditambahkan. Silakan coba lagi.']]
            && $storeQueryFailure->redirects === ['master/relation/product-extra/12/create'],
        'store ' . $label . ' query failure cannot report success'
    );
    $safeOperation = $operation === 'parent_lock' ? 'extra_group_lock' : 'product_revalidation';
    mrpe_check(
        $mrpeLogs === [[
            'error',
            'Master_relation::product_extra_store product_id=12 extra_group_id=7 operation=' . $safeOperation . ' error_code=1205',
        ]]
            && strpos(serialize([$mrpeLogs, $storeQueryFailure->session->flashes]), 'SECRET_') === false
            && strpos(serialize($mrpeLogs), 'SELECT ') === false,
        'store ' . $label . ' failure logs only numeric code and generic operation context'
    );
}

$storeReadBoundaryFailures = [
    'parent prepare' => ['parent_lock', 'extra_group_lock', [
        'prepare_result' => false,
        'connection_errno' => 1205,
        'connection_error' => 'SECRET_PARENT_PREPARE_SQL_DETAIL',
    ]],
    'product bind' => ['product_read', 'product_revalidation', [
        'bind_result' => false,
        'statement_errno' => 1213,
        'statement_error' => 'SECRET_PRODUCT_BIND_SQL_DETAIL',
    ]],
    'duplicate execute' => ['duplicate_read', 'duplicate_check', [
        'execute_result' => false,
        'statement_errno' => 1205,
        'statement_error' => 'SECRET_DUPLICATE_EXECUTE_SQL_DETAIL',
    ]],
    'duplicate result bind' => ['duplicate_read', 'duplicate_check', [
        'bind_result_result' => false,
        'statement_errno' => 2014,
        'statement_error' => 'SECRET_DUPLICATE_RESULT_SQL_DETAIL',
    ]],
    'duplicate fetch' => ['duplicate_read', 'duplicate_check', [
        'fetch_result' => false,
        'statement_errno' => 2013,
        'statement_error' => 'SECRET_DUPLICATE_FETCH_SQL_DETAIL',
    ]],
];
foreach ($storeReadBoundaryFailures as $label => [$operation, $safeOperation, $failureBehavior]) {
    [$readFailureController, $readFailureResponse] = mrpe_invoke_writer(
        'product_extra_store',
        'POST',
        $validStorePost,
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        [],
        [],
        [],
        ['prepared' => [$operation => $failureBehavior]]
    );
    $expectedErrorCode = (int)($failureBehavior['statement_errno'] ?? $failureBehavior['connection_errno'] ?? 0);
    mrpe_check($readFailureResponse === null, 'store ' . $label . ' failure is contained');
    mrpe_check(
        in_array('trans_rollback', $readFailureController->db->events, true)
            && !in_array('insert:execute', $readFailureController->db->events, true)
            && !in_array('trans_commit', $readFailureController->db->events, true)
            && $readFailureController->session->flashes === [['error', 'Mapping product-extra gagal ditambahkan. Silakan coba lagi.']],
        'store ' . $label . ' failure rolls back without insert, commit, or false success'
    );
    mrpe_check(
        $mrpeLogs === [[
            'error',
            'Master_relation::product_extra_store product_id=12 extra_group_id=7 operation=' . $safeOperation . ' error_code=' . $expectedErrorCode,
        ]]
            && strpos(serialize([$mrpeLogs, $readFailureController->session->flashes]), 'SECRET_') === false
            && strpos(serialize($mrpeLogs), 'SELECT ') === false,
        'store ' . $label . ' failure emits only safe numeric/context logging'
    );
    mrpe_check($readFailureController->db->db_debug === true, 'store ' . $label . ' restores db_debug');
}

foreach ([
    'transaction status' => ['trans_status_result' => false],
    'commit' => ['trans_commit_result' => false],
] as $label => $behavior) {
    [$storeFinalizationFailure, $storeFinalizationResponse] = mrpe_invoke_writer(
        'product_extra_store',
        'POST',
        $validStorePost,
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        [],
        [],
        [],
        $behavior
    );
    mrpe_check($storeFinalizationResponse === null, 'store ' . $label . ' failure is contained');
    mrpe_check(
        in_array('insert:execute', $storeFinalizationFailure->db->events, true)
            && in_array('trans_rollback', $storeFinalizationFailure->db->events, true)
            && !in_array(['success', 'Mapping product-extra berhasil ditambahkan.'], $storeFinalizationFailure->session->flashes, true),
        'store ' . $label . ' failure rolls back executed work without false success'
    );
    mrpe_check(
        $storeFinalizationFailure->session->flashes === [['error', 'Mapping product-extra gagal ditambahkan. Silakan coba lagi.']]
            && $storeFinalizationFailure->redirects === ['master/relation/product-extra/12/create'],
        'store ' . $label . ' failure returns the generic error path'
    );
}

[$matchingController, $matchingResponse] = mrpe_invoke_writer(
    'product_extra_store',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => '7', 'sort_order' => 2],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [
        'mst_product' => [12 => $activeProduct],
        'mst_extra_group' => [7 => $matchingGroup],
    ]
);
mrpe_check($matchingResponse === null, 'matching-division group is accepted');
mrpe_check(
    mrpe_prepared_statement($matchingController, 'insert') instanceof MasterRelationProductExtraSmokeStatement,
    'matching-division group reaches the prepared mapping insert'
);

$invalidIdCases = [
    'missing extra_group_id' => [],
    'null extra_group_id' => ['extra_group_id' => null],
    'non-scalar extra_group_id' => ['extra_group_id' => ['7']],
    'zero integer extra_group_id' => ['extra_group_id' => 0],
    'zero string extra_group_id' => ['extra_group_id' => '0'],
    'negative extra_group_id' => ['extra_group_id' => -7],
    'float extra_group_id' => ['extra_group_id' => 7.0],
    'boolean extra_group_id' => ['extra_group_id' => true],
    'malformed extra_group_id' => ['extra_group_id' => '7x'],
    'non-canonical numeric extra_group_id' => ['extra_group_id' => '07'],
    'overflow extra_group_id' => ['extra_group_id' => '999999999999999999999999999999999999'],
];
foreach ($invalidIdCases as $label => $businessPost) {
    [$invalidIdController, $invalidIdResponse] = mrpe_invoke_writer(
        'product_extra_store',
        'POST',
        [MRPE_FIELD => MRPE_TOKEN] + $businessPost
    );
    mrpe_check($invalidIdResponse === null, $label . ' is rejected through legacy flash/redirect flow');
    mrpe_rejected_store_has_no_write($invalidIdController, $label);
    mrpe_check(
        !mrpe_has_call($invalidIdController->Master_model->calls, 'get_by_id', 'mst_extra_group'),
        $label . ' stops before extra-group lookup'
    );
}

$invalidEntityCases = [
    'missing product' => [
        ['mst_product' => []],
        7,
    ],
    'inactive product' => [
        ['mst_product' => [12 => $inactiveProduct]],
        7,
    ],
    'nonexistent group' => [
        ['mst_product' => [12 => $activeProduct], 'mst_extra_group' => []],
        7,
    ],
    'inactive group' => [
        ['mst_product' => [12 => $activeProduct], 'mst_extra_group' => [7 => $inactiveGroup]],
        7,
    ],
    'non-null group division mismatch' => [
        ['mst_product' => [12 => $activeProduct], 'mst_extra_group' => [7 => $mismatchedGroup]],
        7,
    ],
];
foreach ($invalidEntityCases as $label => [$modelFixtures, $extraGroupId]) {
    [$invalidEntityController, $invalidEntityResponse] = mrpe_invoke_writer(
        'product_extra_store',
        'POST',
        [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => $extraGroupId],
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        $modelFixtures
    );
    mrpe_check($invalidEntityResponse === null, $label . ' is rejected through legacy flash/redirect flow');
    mrpe_rejected_store_has_no_write($invalidEntityController, $label);
}

[$duplicateController, $duplicateResponse] = mrpe_invoke_writer(
    'product_extra_store',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => '7', 'sort_order' => 5],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [
        'mst_product' => [12 => $activeProduct],
        'mst_extra_group' => [7 => $matchingGroup],
    ],
    [['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]
);
mrpe_check($duplicateResponse === null, 'valid duplicate retains legacy non-error flow');
mrpe_check(count($duplicateController->session->flashes) === 1 && $duplicateController->session->flashes[0] === ['warning', 'Mapping sudah ada.'], 'valid duplicate retains the old warning');
mrpe_check($duplicateController->redirects === ['master/relation/product-extra/12'], 'valid duplicate retains the old list redirect');
mrpe_check(
        !mrpe_has_call($duplicateController->db->calls, 'insert', 'mst_product_extra_map')
        && !mrpe_has_call($duplicateController->Master_model->calls, 'insert', 'mst_product_extra_map')
        && mrpe_prepared_statement($duplicateController, 'insert') === null,
    'valid duplicate remains insert-free'
);

[$raceDuplicateController, $raceDuplicateResponse] = mrpe_invoke_writer(
    'product_extra_store',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => '7', 'sort_order' => 6],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [
        'mst_product' => [12 => $activeProduct],
        'mst_extra_group' => [7 => $matchingGroup],
    ],
    [],
    [],
    [
        'db_debug' => true,
        'execute_result' => false,
        'statement_errno' => 1062,
        'statement_error' => 'SECRET_SQL_ERROR_MARKER duplicate entry SQL detail',
        'connection_error' => 'SECRET_CONNECTION_MARKER duplicate SQL detail',
    ]
);
mrpe_check($raceDuplicateResponse === null, 'concurrent unique-key duplicate is handled without an exception response');
mrpe_check(
    count($raceDuplicateController->db->conn_id->calls) === 4
        && mrpe_prepared_statement($raceDuplicateController, 'insert')->calls === [
            ['bind_param', 'iii', 12, 7, 6],
            ['execute'],
            ['close'],
        ],
    'race duplicate reaches the prepared execute boundary with integer binds'
);
mrpe_check(
    !mrpe_has_call($raceDuplicateController->db->calls, 'insert', 'mst_product_extra_map'),
    'race duplicate never reaches framework db insert logging'
);
mrpe_check(count($raceDuplicateController->session->flashes) === 1 && $raceDuplicateController->session->flashes[0] === ['warning', 'Mapping sudah ada.'], 'race duplicate returns only the established warning without false success');
mrpe_check($raceDuplicateController->redirects === ['master/relation/product-extra/12'], 'race duplicate redirects to the mapping list');
mrpe_check($raceDuplicateController->db->db_debug === true, 'race duplicate restores the prior db_debug value');
mrpe_check(
    in_array('trans_rollback', $raceDuplicateController->db->events, true)
        && !in_array('trans_commit', $raceDuplicateController->db->events, true),
    '1062 duplicate rolls back and never commits the failed insert transaction'
);
mrpe_check(
    $mrpeLogs === []
        && strpos(serialize($raceDuplicateController->session->flashes), 'SECRET_') === false,
    'race duplicate cannot leak SQL/error markers into the safe log sink or user warning'
);

[$failedInsertController, $failedInsertResponse] = mrpe_invoke_writer(
    'product_extra_store',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => '7'],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [
        'mst_product' => [12 => $activeProduct],
        'mst_extra_group' => [7 => $matchingGroup],
    ],
    [],
    [],
    [
        'db_debug' => false,
        'execute_result' => false,
        'statement_errno' => 1452,
        'statement_error' => 'SECRET_SQL_ERROR_MARKER foreign key SQL detail',
        'connection_error' => 'SECRET_CONNECTION_MARKER foreign key SQL detail',
    ]
);
mrpe_check($failedInsertResponse === null, 'nonduplicate DB failure is handled without exposing an exception response');
mrpe_check(
    count($failedInsertController->session->flashes) === 1
        && $failedInsertController->session->flashes[0] === ['error', 'Mapping product-extra gagal ditambahkan. Silakan coba lagi.'],
    'nonduplicate DB failure returns one safe generic error without false success'
);
mrpe_check($failedInsertController->redirects === ['master/relation/product-extra/12/create'], 'nonduplicate DB failure redirects back to create');
mrpe_check($failedInsertController->db->db_debug === false, 'nonduplicate DB failure restores a false prior db_debug value');
mrpe_check(
    in_array('trans_rollback', $failedInsertController->db->events, true)
        && !in_array('trans_commit', $failedInsertController->db->events, true),
    'nonduplicate execute failure rolls back and never commits'
);
mrpe_check(
    !mrpe_has_call($failedInsertController->db->calls, 'insert', 'mst_product_extra_map')
        && mrpe_prepared_statement($failedInsertController, 'insert')->calls === [
            ['bind_param', 'iii', 12, 7, 0],
            ['execute'],
            ['close'],
        ],
    '1452 failure stays on the prepared boundary and bypasses framework insert logging'
);
mrpe_check(
    $mrpeLogs === [[
        'error',
        'Master_relation::product_extra_store product_id=12 extra_group_id=7 error_code=1452',
    ]],
    'nonduplicate DB failure logs only safe method/product/group/error-code context'
);
mrpe_check(
    strpos(serialize([$mrpeLogs, $failedInsertController->session->flashes]), 'SECRET_') === false
        && strpos(serialize($mrpeLogs), 'INSERT INTO') === false,
    '1452 SQL, DB error, and secret markers are absent from the safe log sink and user flash'
);

[$throwingInsertController, $throwingInsertResponse] = mrpe_invoke_writer(
    'product_extra_store',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => '7'],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [
        'mst_product' => [12 => $activeProduct],
        'mst_extra_group' => [7 => $matchingGroup],
    ],
    [],
    [],
    [
        'db_debug' => true,
        'execute_throwable' => new RuntimeException('SECRET_THROWABLE_MARKER SQL detail', 999),
        'statement_error' => 'SECRET_STATEMENT_MARKER SQL detail',
        'connection_error' => 'SECRET_CONNECTION_MARKER SQL detail',
    ]
);
mrpe_check($throwingInsertResponse === null, 'insert throwable is contained by the generic failure flow');
mrpe_check($throwingInsertController->db->db_debug === true, 'insert throwable restores db_debug through finally');
mrpe_check(
    count($throwingInsertController->session->flashes) === 1
        && $throwingInsertController->session->flashes[0] === ['error', 'Mapping product-extra gagal ditambahkan. Silakan coba lagi.']
        && strpos(serialize($throwingInsertController->session->flashes), 'SECRET') === false,
    'insert throwable returns one generic flash without exception detail or false success'
);
mrpe_check($throwingInsertController->redirects === ['master/relation/product-extra/12/create'], 'insert throwable redirects back to create');
mrpe_check(
    $mrpeLogs === [[
        'error',
        'Master_relation::product_extra_store product_id=12 extra_group_id=7 error_code=0',
    ]]
        && strpos(serialize($mrpeLogs), 'SECRET_') === false
        && strpos(serialize($mrpeLogs), 'INSERT INTO') === false,
    'insert throwable ignores exception code/message and logs only safe numeric DB context'
);

$preparedFailureCases = [
    'unsupported driver' => [
        'behavior' => ['db_debug' => false, 'dbdriver' => 'pdo'],
        'log' => 'Master_relation::product_extra_store product_id=12 extra_group_id=7 operation=extra_group_lock error_code=0',
        'connection_calls' => 0,
        'statement_count' => 0,
    ],
    'unsupported connection' => [
        'behavior' => ['db_debug' => true, 'unsupported_connection' => true],
        'log' => 'Master_relation::product_extra_store product_id=12 extra_group_id=7 operation=extra_group_lock error_code=0',
        'connection_calls' => null,
        'statement_count' => 0,
    ],
    'prepare failure' => [
        'behavior' => [
            'db_debug' => false,
            'prepare_result' => false,
            'connection_errno' => 1452,
            'connection_error' => 'SECRET_PREPARE_MARKER SQL detail',
        ],
        'log' => 'Master_relation::product_extra_store product_id=12 extra_group_id=7 error_code=1452',
        'connection_calls' => 4,
        'statement_count' => 3,
    ],
    'bind failure' => [
        'behavior' => [
            'db_debug' => true,
            'bind_result' => false,
            'statement_errno' => 1452,
            'statement_error' => 'SECRET_BIND_MARKER SQL detail',
        ],
        'log' => 'Master_relation::product_extra_store product_id=12 extra_group_id=7 error_code=1452',
        'connection_calls' => 4,
        'statement_count' => 4,
    ],
];
foreach ($preparedFailureCases as $label => $case) {
    [$boundaryController, $boundaryResponse] = mrpe_invoke_writer(
        'product_extra_store',
        'POST',
        [MRPE_FIELD => MRPE_TOKEN, 'extra_group_id' => '7'],
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        [
            'mst_product' => [12 => $activeProduct],
            'mst_extra_group' => [7 => $matchingGroup],
        ],
        [],
        [],
        $case['behavior']
    );
    mrpe_check($boundaryResponse === null, $label . ' remains contained in the legacy response flow');
    mrpe_check(
        $boundaryController->session->flashes === [[
            'error',
            'Mapping product-extra gagal ditambahkan. Silakan coba lagi.',
        ]]
            && $boundaryController->redirects === ['master/relation/product-extra/12/create'],
        $label . ' returns the generic error and create redirect'
    );
    mrpe_check(
        $boundaryController->db->db_debug === (bool)$case['behavior']['db_debug'],
        $label . ' restores the prior db_debug value'
    );
    mrpe_check(
        !mrpe_has_call($boundaryController->db->calls, 'insert', 'mst_product_extra_map'),
        $label . ' never falls back to framework db insert'
    );
    mrpe_check(
        $mrpeLogs === [[
            'error',
            $case['log'],
        ]]
            && strpos(serialize([$mrpeLogs, $boundaryController->session->flashes]), 'SECRET_') === false
            && strpos(serialize($mrpeLogs), 'INSERT INTO') === false,
        $label . ' logs numeric context only without SQL/error markers'
    );
    if ($case['connection_calls'] !== null) {
        mrpe_check(
            count($boundaryController->db->conn_id->calls) === $case['connection_calls']
                && mrpe_prepared_statement_count($boundaryController) === $case['statement_count'],
            $label . ' stops at the expected prepared-statement boundary'
        );
    }
}

$mappingLookupFailureCases = [
    'prepare' => [
        'prepare_result' => false,
        'connection_errno' => 1205,
        'connection_error' => 'SECRET_LOOKUP_PREPARE_SQL_DETAIL',
    ],
    'bind' => [
        'bind_result' => false,
        'statement_errno' => 1213,
        'statement_error' => 'SECRET_LOOKUP_BIND_SQL_DETAIL',
    ],
    'execute' => [
        'execute_result' => false,
        'statement_errno' => 2013,
        'statement_error' => 'SECRET_LOOKUP_EXECUTE_SQL_DETAIL',
    ],
    'result bind' => [
        'bind_result_result' => false,
        'statement_errno' => 2014,
        'statement_error' => 'SECRET_LOOKUP_RESULT_SQL_DETAIL',
    ],
    'fetch' => [
        'fetch_result' => false,
        'statement_errno' => 2006,
        'statement_error' => 'SECRET_LOOKUP_FETCH_SQL_DETAIL',
    ],
];
foreach ($mappingLookupFailureCases as $label => $failureBehavior) {
    [$lookupFailureController, $lookupFailureResponse] = mrpe_invoke_writer(
        'product_extra_delete',
        'POST',
        [MRPE_FIELD => MRPE_TOKEN],
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        ['mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]],
        [],
        [],
        ['prepared' => ['mapping_lookup' => $failureBehavior]]
    );
    $expectedErrorCode = (int)($failureBehavior['statement_errno'] ?? $failureBehavior['connection_errno'] ?? 0);
    mrpe_check($lookupFailureResponse === null, 'initial mapping lookup ' . $label . ' failure is contained');
    mrpe_check(
        !in_array('trans_begin', $lookupFailureController->db->events, true)
            && !in_array('trans_rollback', $lookupFailureController->db->events, true)
            && !in_array('trans_commit', $lookupFailureController->db->events, true)
            && $lookupFailureController->session->flashes === [['error', 'Mapping product-extra gagal dihapus. Silakan coba lagi.']]
            && $lookupFailureController->redirects === ['master/relation/product-extra'],
        'initial mapping lookup ' . $label . ' stops before transaction and cannot report success'
    );
    mrpe_check(
        $mrpeLogs === [[
            'error',
            'Master_relation::product_extra_delete mapping_id=91 operation=mapping_lookup error_code=' . $expectedErrorCode,
        ]]
            && strpos(serialize([$mrpeLogs, $lookupFailureController->session->flashes]), 'SECRET_') === false
            && strpos(serialize($mrpeLogs), 'SELECT ') === false,
        'initial mapping lookup ' . $label . ' logs only numeric code and generic context'
    );
    mrpe_check($lookupFailureController->db->db_debug === true, 'initial mapping lookup ' . $label . ' restores db_debug');
}

[$missingDeleteController, $missingDeleteResponse] = mrpe_invoke_writer(
    'product_extra_delete',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    ['mst_product_extra_map' => []]
);
mrpe_check(
    $missingDeleteResponse instanceof MasterRelationProductExtraSmokeResponse
        && $missingDeleteResponse->getCode() === 404,
    'missing initial mapping preserves HTTP 404 semantics'
);
mrpe_check(
    $missingDeleteController->db->db_debug === true
        && !in_array('trans_begin', $missingDeleteController->db->events, true)
        && $missingDeleteController->session->flashes === [],
    'missing initial mapping restores db_debug and stops before transaction or flash'
);

[$deleteController, $deleteResponse] = mrpe_invoke_writer(
    'product_extra_delete',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [
        'mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]],
        'mst_product' => [12 => $inactiveProduct],
        'mst_extra_group' => [7 => $inactiveGroup],
    ]
);
mrpe_check($deleteResponse === null, 'product_extra_delete valid form request completes');
mrpe_check($deleteController->permissionCalls === [[MRPE_PAGE, 'delete']], 'product_extra_delete valid request preserves delete RBAC');
mrpe_check(
    mrpe_prepared_statement($deleteController, 'delete')->calls === [
        ['bind_param', 'ii', 91, 7],
        ['execute'],
        ['close'],
    ] && !mrpe_has_call($deleteController->db->calls, 'delete', 'mst_product_extra_map'),
    'product_extra_delete reaches only the exact prepared delete with integer binds'
);
mrpe_check(
    mrpe_has_call($deleteController->db->calls, 'insert', 'aud_transaction_log'),
    'successful delete writes its audit row in the existing transaction'
);
mrpe_check(
    $deleteController->Master_model->calls === []
        && array_column($deleteController->db->conn_id->calls, 'operation') === [
            'mapping_lookup', 'delete_parent_lock', 'mapping_lock', 'delete',
        ],
    'product_extra_delete performs direct mapping read and does not gate historical rows on active state'
);
mrpe_check(
    mrpe_prepared_statement($deleteController, 'mapping_lookup')->calls === [
        ['bind_param', 'i', 91], ['execute'], ['bind_result', 2], ['fetch'], ['close'],
    ]
        && mrpe_prepared_statement($deleteController, 'delete_parent_lock')->calls === [
            ['bind_param', 'i', 7], ['execute'], ['bind_result', 1], ['fetch'], ['close'],
        ]
        && mrpe_prepared_statement($deleteController, 'mapping_lock')->calls === [
            ['bind_param', 'ii', 91, 7], ['execute'], ['bind_result', 3], ['fetch'], ['close'],
        ],
    'delete mapping lookup, parent lock, and mapping lock use prepared integer binds and portable result handling'
);
mrpe_check(strpos(mrpe_method_source($controllerSource, 'product_extra_delete'), "'is_active'") === false, 'product_extra_delete source remains free of active policy');
mrpe_check(
    mrpe_event_before($deleteController->db->events, 'trans_begin', 'lock:extra_group')
        && mrpe_event_before($deleteController->db->events, 'lock:extra_group', 'lock:mapping')
        && mrpe_event_before($deleteController->db->events, 'lock:mapping', 'delete:mst_product_extra_map')
        && mrpe_event_before($deleteController->db->events, 'delete:mst_product_extra_map', 'trans_commit'),
    'delete with an existing inactive parent locks parent then exact mapping and verifies DML before commit'
);
mrpe_check(
    $deleteController->session->flashes === [['success', 'Mapping product-extra berhasil dihapus.']]
        && $deleteController->db->db_debug === true
        && $mrpeLogs === [],
    'delete applies both exact-row predicates and preserves inactive historical cleanup success'
);

[$orphanDeleteController, $orphanDeleteResponse] = mrpe_invoke_writer(
    'product_extra_delete',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    [
        'mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]],
        'mst_extra_group' => [],
    ]
);
mrpe_check($orphanDeleteResponse === null, 'historical orphan mapping delete remains supported');
mrpe_check(
    mrpe_event_before($orphanDeleteController->db->events, 'lock:extra_group', 'lock:mapping')
        && mrpe_event_before($orphanDeleteController->db->events, 'lock:mapping', 'delete:mst_product_extra_map')
        && in_array('trans_commit', $orphanDeleteController->db->events, true),
    'missing-parent path locks the surviving exact mapping before delete and commit'
);
mrpe_check(
    $orphanDeleteController->session->flashes === [['success', 'Mapping product-extra berhasil dihapus.']]
        && $orphanDeleteController->redirects === ['master/relation/product-extra/12'],
    'orphan cleanup returns the established success only after commit'
);

[$staleDeleteController, $staleDeleteResponse] = mrpe_invoke_writer(
    'product_extra_delete',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    ['mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]],
    [],
    [],
    ['mapping_lock_rows' => []]
);
mrpe_check($staleDeleteResponse === null, 'mapping disappearance after initial lookup is handled without exception');
mrpe_check(
    in_array('trans_rollback', $staleDeleteController->db->events, true)
        && !in_array('delete:mst_product_extra_map', $staleDeleteController->db->events, true)
        && !in_array('trans_commit', $staleDeleteController->db->events, true),
    'stale mapping lock result rolls back without delete or commit'
);
mrpe_check(
    $staleDeleteController->session->flashes === [['warning', 'Mapping berubah. Muat ulang data sebelum menghapus.']]
        && $staleDeleteController->redirects === ['master/relation/product-extra/12'],
    'stale mapping result warns to reload and never reports success'
);

[$zeroDeleteController, $zeroDeleteResponse] = mrpe_invoke_writer(
    'product_extra_delete',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    ['mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]],
    [],
    [],
    ['affected_rows' => 0]
);
mrpe_check($zeroDeleteResponse === null, 'zero-row exact delete is handled without exception');
mrpe_check(
    in_array('delete:mst_product_extra_map', $zeroDeleteController->db->events, true)
        && in_array('trans_rollback', $zeroDeleteController->db->events, true)
        && !in_array('trans_commit', $zeroDeleteController->db->events, true),
    'zero-row delete rolls back and cannot commit'
);
mrpe_check(
    $zeroDeleteController->session->flashes === [['warning', 'Mapping berubah. Muat ulang data sebelum menghapus.']]
        && !in_array(['success', 'Mapping product-extra berhasil dihapus.'], $zeroDeleteController->session->flashes, true),
    'zero-row delete warns about stale state without false success'
);

[$deleteBeginFailure, $deleteBeginFailureResponse] = mrpe_invoke_writer(
    'product_extra_delete',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    ['mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]],
    [],
    [],
    ['trans_begin_result' => false]
);
mrpe_check($deleteBeginFailureResponse === null, 'delete begin failure stays in the generic response flow');
mrpe_check(
    $deleteBeginFailure->db->events === ['lookup:mapping', 'trans_begin']
        && $deleteBeginFailure->session->flashes === [['error', 'Mapping product-extra gagal dihapus. Silakan coba lagi.']],
    'delete begin failure performs no lock/DML and cannot report success'
);

$deleteFailureCases = [
    'parent query' => ['prepared' => ['delete_parent_lock' => ['execute_result' => false, 'statement_errno' => 1205, 'statement_error' => 'SECRET_PARENT_SQL']]],
    'mapping query' => ['prepared' => ['mapping_lock' => ['execute_result' => false, 'statement_errno' => 1205, 'statement_error' => 'SECRET_MAPPING_SQL']]],
    'delete query' => ['prepared' => ['delete' => ['execute_result' => false, 'statement_errno' => 1213, 'statement_error' => 'SECRET_DELETE_SQL']]],
    'transaction status' => ['trans_status_result' => false],
    'commit' => ['trans_commit_result' => false],
];
foreach ($deleteFailureCases as $label => $behavior) {
    [$deleteFailureController, $deleteFailureResponse] = mrpe_invoke_writer(
        'product_extra_delete',
        'POST',
        [MRPE_FIELD => MRPE_TOKEN],
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        ['mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]],
        [],
        [],
        $behavior
    );
    mrpe_check($deleteFailureResponse === null, 'delete ' . $label . ' failure is contained');
    mrpe_check(
        in_array('trans_rollback', $deleteFailureController->db->events, true)
            && !in_array(['success', 'Mapping product-extra berhasil dihapus.'], $deleteFailureController->session->flashes, true),
        'delete ' . $label . ' failure rolls back without false success'
    );
    mrpe_check(
        $deleteFailureController->session->flashes === [['error', 'Mapping product-extra gagal dihapus. Silakan coba lagi.']]
            && $deleteFailureController->redirects === ['master/relation/product-extra/12']
            && strpos(serialize([$mrpeLogs, $deleteFailureController->session->flashes]), 'SECRET_') === false,
        'delete ' . $label . ' failure returns generic redacted context only'
    );
}

$deletePreparedBoundaryFailures = [
    'parent prepare' => ['delete_parent_lock', 'extra_group_lock', [
        'prepare_result' => false,
        'connection_errno' => 1205,
        'connection_error' => 'SECRET_DELETE_PARENT_PREPARE_SQL_DETAIL',
    ]],
    'mapping bind' => ['mapping_lock', 'mapping_lock', [
        'bind_result' => false,
        'statement_errno' => 1213,
        'statement_error' => 'SECRET_MAPPING_BIND_SQL_DETAIL',
    ]],
    'mapping result bind' => ['mapping_lock', 'mapping_lock', [
        'bind_result_result' => false,
        'statement_errno' => 2014,
        'statement_error' => 'SECRET_MAPPING_RESULT_SQL_DETAIL',
    ]],
    'mapping fetch' => ['mapping_lock', 'mapping_lock', [
        'fetch_result' => false,
        'statement_errno' => 2013,
        'statement_error' => 'SECRET_MAPPING_FETCH_SQL_DETAIL',
    ]],
    'delete prepare' => ['delete', 'mapping_delete', [
        'prepare_result' => false,
        'connection_errno' => 1205,
        'connection_error' => 'SECRET_DELETE_PREPARE_SQL_DETAIL',
    ]],
    'delete bind' => ['delete', 'mapping_delete', [
        'bind_result' => false,
        'statement_errno' => 1213,
        'statement_error' => 'SECRET_DELETE_BIND_SQL_DETAIL',
    ]],
    'delete execute' => ['delete', 'mapping_delete', [
        'execute_result' => false,
        'statement_errno' => 1451,
        'statement_error' => 'SECRET_DELETE_EXECUTE_SQL_DETAIL',
    ]],
];
foreach ($deletePreparedBoundaryFailures as $label => [$operation, $safeOperation, $failureBehavior]) {
    [$preparedDeleteFailure, $preparedDeleteResponse] = mrpe_invoke_writer(
        'product_extra_delete',
        'POST',
        [MRPE_FIELD => MRPE_TOKEN],
        true,
        [MRPE_FIELD => MRPE_TOKEN],
        [],
        [],
        '',
        ['mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]],
        [],
        [],
        ['prepared' => [$operation => $failureBehavior]]
    );
    $expectedErrorCode = (int)($failureBehavior['statement_errno'] ?? $failureBehavior['connection_errno'] ?? 0);
    mrpe_check($preparedDeleteResponse === null, $label . ' failure is contained');
    mrpe_check(
        in_array('trans_rollback', $preparedDeleteFailure->db->events, true)
            && !in_array('trans_commit', $preparedDeleteFailure->db->events, true)
            && !in_array(['success', 'Mapping product-extra berhasil dihapus.'], $preparedDeleteFailure->session->flashes, true)
            && $preparedDeleteFailure->session->flashes === [['error', 'Mapping product-extra gagal dihapus. Silakan coba lagi.']],
        $label . ' failure rolls back and cannot report success'
    );
    mrpe_check(
        $mrpeLogs === [[
            'error',
            'Master_relation::product_extra_delete mapping_id=91 product_id=12 extra_group_id=7 operation=' . $safeOperation . ' error_code=' . $expectedErrorCode,
        ]]
            && strpos(serialize([$mrpeLogs, $preparedDeleteFailure->session->flashes]), 'SECRET_') === false
            && strpos(serialize($mrpeLogs), 'SELECT ') === false
            && strpos(serialize($mrpeLogs), 'DELETE FROM') === false,
        $label . ' failure logs only numeric code and generic operation context'
    );
    mrpe_check($preparedDeleteFailure->db->db_debug === true, $label . ' failure restores db_debug');
}

[$multiDeleteController, $multiDeleteResponse] = mrpe_invoke_writer(
    'product_extra_delete',
    'POST',
    [MRPE_FIELD => MRPE_TOKEN],
    true,
    [MRPE_FIELD => MRPE_TOKEN],
    [],
    [],
    '',
    ['mst_product_extra_map' => [91 => ['id' => 91, 'product_id' => 12, 'extra_group_id' => 7]]],
    [],
    [],
    ['prepared' => ['delete' => ['affected_rows' => 2]]]
);
mrpe_check($multiDeleteResponse === null, 'multi-row exact delete anomaly is contained');
mrpe_check(
    in_array('trans_rollback', $multiDeleteController->db->events, true)
        && !in_array('trans_commit', $multiDeleteController->db->events, true)
        && $multiDeleteController->session->flashes === [['warning', 'Mapping berubah. Muat ulang data sebelum menghapus.']],
    'affected rows other than exactly one rolls back with stale warning and no false success'
);

[$newTokenController, $newTokenReflection] = mrpe_fixture('GET', [], true, []);
$tokenHelper = $newTokenReflection->getMethod('productExtraMutationCsrf');
$tokenHelper->setAccessible(true);
$generated = (string)$tokenHelper->invoke($newTokenController);
mrpe_check(preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1, 'missing token generates strict lowercase 64-hex');
mrpe_check(
    count($newTokenController->session->writes) === 1
        && $newTokenController->session->writes[0][0] === MRPE_FIELD,
    'generated token is stored only under the product-extra key'
);

[$malformedController, $malformedReflection] = mrpe_fixture('GET', [], true, [MRPE_FIELD => strtoupper(MRPE_TOKEN)]);
$malformedHelper = $malformedReflection->getMethod('productExtraMutationCsrf');
$malformedHelper->setAccessible(true);
$regenerated = (string)$malformedHelper->invoke($malformedController);
mrpe_check(preg_match('/\A[0-9a-f]{64}\z/D', $regenerated) === 1 && $regenerated !== strtoupper(MRPE_TOKEN), 'malformed session token is regenerated');

$renders = [
    'product_extra' => ['view', 12],
    'product_extra_create' => ['create', 12],
];
foreach ($renders as $renderMethod => [$action, $id]) {
    $renderSource = mrpe_method_source($controllerSource, $renderMethod);
    $permissionAt = strpos($renderSource, "requireRelationPermission('extra', '" . $action . "')");
    $tokenAt = strpos($renderSource, 'productExtraMutationCsrf()');
    mrpe_check(
        $permissionAt !== false && $tokenAt !== false && $permissionAt < $tokenAt
            && strpos($renderSource, "'" . MRPE_FIELD . "' =>") !== false
            && strpos($renderSource, 'productRecipeMutationCsrf()') === false
            && strpos($renderSource, 'componentFormulaMutationCsrf()') === false,
        $renderMethod . ' supplies only product-extra token after canonical RBAC'
    );

    [$authorized] = mrpe_fixture('GET', [], true, []);
    $authorized->{$renderMethod}($id);
    mrpe_check(
        preg_match('/\A[0-9a-f]{64}\z/D', (string)($authorized->renderedData[MRPE_FIELD] ?? '')) === 1
            && !array_key_exists(MRPE_RECIPE_FIELD, $authorized->renderedData)
            && !array_key_exists(MRPE_FORMULA_FIELD, $authorized->renderedData),
        $renderMethod . ' authorized render receives only its scoped token'
    );

    [$denied] = mrpe_fixture('GET', [], false, []);
    $deniedResponse = null;
    try {
        $denied->{$renderMethod}($id);
    } catch (MasterRelationProductExtraSmokeResponse $exception) {
        $deniedResponse = $exception;
    }
    mrpe_check($deniedResponse instanceof MasterRelationProductExtraSmokeResponse && $deniedResponse->getCode() === 403, $renderMethod . ' denied render is blocked by RBAC');
    mrpe_check(
        $denied->session->reads === [] && $denied->session->writes === []
            && $denied->Master_model->calls === [] && $denied->db->calls === [],
        $renderMethod . ' denied render does not generate token or read business data'
    );
}

[$hubController] = mrpe_fixture('GET', [], true, []);
$hubController->product_extra_hub();
mrpe_check($hubController->permissionCalls === [[MRPE_PAGE, 'view']], 'product_extra_hub preserves view RBAC');
mrpe_check($hubController->session->reads === [] && $hubController->session->writes === [], 'product_extra_hub does not create or expose a mutation token');
mrpe_check(!array_key_exists(MRPE_FIELD, $hubController->renderedData), 'product_extra_hub render data remains token-free');

mrpe_check(substr_count($formViewSource, 'name="' . MRPE_FIELD . '"') === 1, 'generic relation form has exactly one product-extra hidden token');
$extraFormBranchAt = strpos($formViewSource, '<?php if ($isProductExtra): ?>');
$extraFormTokenAt = strpos($formViewSource, 'name="' . MRPE_FIELD . '"');
$extraFormBranchEnd = strpos($formViewSource, '<?php endif; ?>', (int)$extraFormBranchAt);
mrpe_check(
    $extraFormBranchAt !== false && $extraFormTokenAt !== false && $extraFormBranchEnd !== false
        && $extraFormBranchAt < $extraFormTokenAt && $extraFormTokenAt < $extraFormBranchEnd,
    'product-extra token is confined to the product-extra generic form branch'
);
mrpe_check(substr_count($formViewSource, 'name="' . MRPE_RECIPE_FIELD . '"') === 1, 'product-recipe form token remains intact');
mrpe_check(substr_count($formViewSource, 'name="' . MRPE_FORMULA_FIELD . '"') === 0, 'retired component-formula form no longer exposes a legacy mutation token');

$extraDeleteForm = '<form method="post" action="<?php echo site_url(\'master/relation/product-extra/delete/\'';
$recipeDeleteForm = '<form method="post" action="<?php echo site_url(\'master/relation/product-recipe/delete/\'';
mrpe_check(
    strpos($listViewSource, $extraDeleteForm) !== false
        && substr_count($listViewSource, 'name="' . MRPE_FIELD . '"') === 1
        && strpos($listViewSource, "onsubmit=\"return confirm('Hapus mapping ini?')\"") !== false
        && strpos($listViewSource, "href=\"<?php echo site_url('master/relation/product-extra/delete/") === false,
    'product-extra delete is a confirmed scoped POST form with no GET fallback'
);
mrpe_check(
    strpos($listViewSource, $recipeDeleteForm) !== false
        && strpos($listViewSource, 'name="' . MRPE_RECIPE_FIELD . '"') !== false,
    'product-recipe delete remains a scoped POST form'
);
mrpe_check(
    strpos($listViewSource, 'master/relation/component-formula/delete/') === false
        && strpos($listViewSource, 'master/relation/component-formula/edit/') === false
        && strpos($listViewSource, 'production/component-formulas/edit/') !== false,
    'retired component-formula list exposes only the canonical editor link'
);

$viewHost = new MasterRelationProductExtraSmokeViewHost();
$baseFormData = [
    'title' => 'Fixture Form',
    'parent' => ['id' => 12, 'product_name' => 'Fixture Product', 'component_name' => 'Fixture Component'],
    'row' => null,
    'form_action' => 'fixture/store',
    'options' => ['extra_groups' => [['value' => '7', 'label' => 'Fixture Group']]],
    'product_variable_cost' => [],
];
$extraFormHtml = $viewHost->render($formViewPath, $baseFormData + [
    'relation_type' => 'product-extra',
    MRPE_FIELD => MRPE_TOKEN,
]);
mrpe_check(substr_count($extraFormHtml, 'name="' . MRPE_FIELD . '"') === 1 && strpos($extraFormHtml, '<form method="post"') !== false, 'rendered product-extra create form submits one scoped token by POST');
mrpe_check(strpos($extraFormHtml, 'name="' . MRPE_RECIPE_FIELD . '"') === false && strpos($extraFormHtml, 'name="' . MRPE_FORMULA_FIELD . '"') === false, 'rendered product-extra form does not expose recipe/formula tokens');

$recipeFormHtml = $viewHost->render($formViewPath, $baseFormData + [
    'relation_type' => 'product-recipe',
    MRPE_RECIPE_FIELD => MRPE_RECIPE_TOKEN,
]);
mrpe_check(strpos($recipeFormHtml, 'name="' . MRPE_RECIPE_FIELD . '"') !== false && strpos($recipeFormHtml, 'name="' . MRPE_FIELD . '"') === false, 'rendered recipe form keeps recipe POST token without product-extra token');

$formulaFormHtml = $viewHost->render($formViewPath, $baseFormData + [
    'relation_type' => 'component-formula',
    MRPE_FORMULA_FIELD => MRPE_FORMULA_TOKEN,
]);
mrpe_check(strpos($formulaFormHtml, 'name="' . MRPE_FORMULA_FIELD . '"') === false && strpos($formulaFormHtml, 'name="' . MRPE_FIELD . '"') === false && strpos($formulaFormHtml, '/smoke/production/component-formulas/edit/12') !== false, 'retired component form exposes no mutation token and returns to canonical editor');

$baseListData = [
    'title' => 'Fixture List',
    'parent' => ['id' => 12, 'product_name' => 'Fixture Product', 'component_name' => 'Fixture Component'],
    'summary' => [],
    'default_source_division' => [],
    'product_variable_cost' => [],
];
$extraListHtml = $viewHost->render($listViewPath, $baseListData + [
    'relation_type' => 'product-extra',
    'rows' => [['id' => 91, 'group_name' => 'Fixture Group', 'sort_order' => 0]],
    MRPE_FIELD => MRPE_TOKEN,
]);
mrpe_check(substr_count($extraListHtml, 'name="' . MRPE_FIELD . '"') === 1 && strpos($extraListHtml, '<form method="post" action="/smoke/master/relation/product-extra/delete/91"') !== false, 'rendered product-extra list exposes one scoped POST delete form');
mrpe_check(strpos($extraListHtml, 'href="/smoke/master/relation/product-extra/delete/91"') === false && strpos($extraListHtml, 'name="' . MRPE_RECIPE_FIELD . '"') === false && strpos($extraListHtml, 'name="' . MRPE_FORMULA_FIELD . '"') === false, 'rendered product-extra list has no GET delete or cross-scope token');

$formulaListHtml = $viewHost->render($listViewPath, $baseListData + [
    'relation_type' => 'component-formula',
    'rows' => [[
        'id' => 91,
        'line_no' => 1,
        'line_type' => 'MATERIAL',
        'item_name' => 'Fixture Material',
        'source_division_name' => 'BAR',
        'qty' => 1,
        'uom_name' => 'PCS',
        'sort_order' => 0,
        'notes' => '',
    ]],
    MRPE_FORMULA_FIELD => MRPE_FORMULA_TOKEN,
]);
mrpe_check(strpos($formulaListHtml, 'name="' . MRPE_FORMULA_FIELD . '"') === false && strpos($formulaListHtml, '<form method="post" action="/smoke/master/relation/component-formula/delete/91"') === false && strpos($formulaListHtml, 'href="/smoke/production/component-formulas/edit/12"') !== false, 'retired component list exposes only the canonical editor');
mrpe_check(strpos($formulaListHtml, 'name="' . MRPE_FIELD . '"') === false, 'retired component list does not expose product-extra token');

$recipeListHtml = $viewHost->render($listViewPath, $baseListData + [
    'relation_type' => 'product-recipe',
    'rows' => [],
    MRPE_RECIPE_FIELD => MRPE_RECIPE_TOKEN,
]);
mrpe_check(strpos($recipeListHtml, 'name="' . MRPE_FIELD . '"') === false, 'rendered recipe list does not expose product-extra token');

if ($failures !== []) {
    fwrite(STDERR, "Master relation product extra mutation CSRF smoke FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("Checks: %d; failures: %d\n", $checks, count($failures)));
    exit(1);
}

fwrite(STDOUT, sprintf("Master relation product extra mutation CSRF smoke passed (%d checks).\n", $checks));
