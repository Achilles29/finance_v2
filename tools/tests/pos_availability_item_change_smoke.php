<?php

declare(strict_types=1);

/**
 * DB/bootstrap-free smoke for item-centric POS availability invalidation.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosAvailabilityItemFakeResult
{
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function result_array(): array
    {
        return $this->rows;
    }

    public function row_array(): array
    {
        return $this->rows[0] ?? [];
    }
}

final class PosAvailabilityItemFakeDb
{
    private string $from = '';
    private array $where = [];

    public function table_exists(string $table): bool
    {
        return in_array($table, ['mst_product_recipe', 'mst_component_formula', 'mst_item', 'pos_outlet'], true);
    }

    public function field_exists(string $field, string $table): bool
    {
        return $field === 'material_id' && in_array($table, ['mst_item', 'mst_component_formula'], true);
    }

    public function select($select, $escape = null): self
    {
        return $this;
    }

    public function from(string $table): self
    {
        $this->from = $table;
        $this->where = [];
        return $this;
    }

    public function join($table, $condition, $type = ''): self
    {
        return $this;
    }

    public function where($key, $value = null, $escape = null): self
    {
        $this->where[(string)$key] = $value;
        return $this;
    }

    public function or_where($key, $value = null, $escape = null): self
    {
        $this->where[(string)$key] = $value;
        return $this;
    }

    public function group_start(): self
    {
        return $this;
    }

    public function or_group_start(): self
    {
        return $this;
    }

    public function group_end(): self
    {
        return $this;
    }

    public function order_by($field, $direction = '', $escape = null): self
    {
        return $this;
    }

    public function limit($limit, $offset = null): self
    {
        return $this;
    }

    public function get(): PosAvailabilityItemFakeResult
    {
        $rows = [];
        if ($this->from === 'mst_product_recipe r' && ($this->where['r.material_item_id'] ?? 0) === 7) {
            $rows = [['product_id' => 101]];
        } elseif ($this->from === 'mst_product_recipe r' && ($this->where['i.material_id'] ?? 0) === 301) {
            $rows = [['product_id' => 102]];
        } elseif ($this->from === 'mst_component_formula f' && ($this->where['f.material_item_id'] ?? 0) === 7) {
            $rows = [['component_id' => 201]];
        } elseif ($this->from === 'mst_component_formula f' && ($this->where['f.material_id'] ?? 0) === 301) {
            $rows = [['component_id' => 202]];
        } elseif ($this->from === 'mst_item' && ($this->where['id'] ?? 0) === 7) {
            $rows = [['material_id' => 301]];
        } elseif ($this->from === 'mst_product_recipe') {
            $productsByComponent = [201 => 103, 202 => 104, 203 => 105];
            $componentId = (int)($this->where['component_id'] ?? 0);
            if (isset($productsByComponent[$componentId])) {
                $rows = [['product_id' => $productsByComponent[$componentId]]];
            }
        } elseif ($this->from === 'mst_component_formula') {
            $componentId = (int)($this->where['sub_component_id'] ?? 0);
            if ($componentId === 201) {
                $rows = [['component_id' => 203]];
            }
        } elseif ($this->from === 'pos_outlet') {
            $rows = [['id' => 11], ['id' => 12]];
        }

        return new PosAvailabilityItemFakeResult($rows);
    }
}

final class PosAvailabilityItemFakeQueue
{
    public array $calls = [];

    public function isReady(): bool
    {
        return true;
    }

    public function enqueueProducts(array $outletIds, array $productIds, array $context): array
    {
        $this->calls[] = compact('outletIds', 'productIds', 'context');
        return ['ok' => true, 'queued_count' => count($outletIds) * count($productIds), 'failed_count' => 0];
    }
}

final class PosAvailabilityItemFakeLoader
{
    public function database(): void
    {
    }

    public function library(string $name): void
    {
    }
}

final class PosAvailabilityItemFakeCi
{
    public PosAvailabilityItemFakeDb $db;
    public PosAvailabilityItemFakeLoader $load;
    public PosAvailabilityItemFakeQueue $posavailabilityqueueservice;

    public function __construct()
    {
        $this->db = new PosAvailabilityItemFakeDb();
        $this->load = new PosAvailabilityItemFakeLoader();
        $this->posavailabilityqueueservice = new PosAvailabilityItemFakeQueue();
    }
}

require dirname(__DIR__, 2) . '/application/libraries/PosAvailabilityRebuildService.php';

$fakeCi = new PosAvailabilityItemFakeCi();
$reflection = new ReflectionClass(PosAvailabilityRebuildService::class);
$service = $reflection->newInstanceWithoutConstructor();
$ciProperty = $reflection->getProperty('ci');
$ciProperty->setAccessible(true);
$ciProperty->setValue($service, $fakeCi);

$failures = [];
$checks = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$resolved = $service->resolve_affected_products_from_item(7);
sort($resolved);
$check($resolved === [101, 102, 103, 104, 105], 'item resolver includes direct recipes, direct/nested components, and legacy material mapping');
$check($service->resolve_affected_products_from_item(0) === [], 'invalid item identity resolves no products');

$result = $service->handle_item_change(7, ['event_source' => 'ITEM_VALUE_CHANGE']);
$queueCalls = $fakeCi->posavailabilityqueueservice->calls;
$queuedProducts = $queueCalls[0]['productIds'] ?? [];
sort($queuedProducts);
$check(($result['ok'] ?? false) === true && ($result['queued_count'] ?? 0) === 10, 'item handler returns the compatible queue result');
$check(count($queueCalls) === 1, 'item handler queues the combined dependency set once');
$check(($queueCalls[0]['outletIds'] ?? []) === [11, 12] && $queuedProducts === [101, 102, 103, 104, 105], 'single queue call covers all outlets and unique affected products');

$source = file_get_contents(dirname(__DIR__, 2) . '/application/libraries/PosAvailabilityRebuildService.php');
$resolverMethod = $reflection->getMethod('resolve_affected_products_from_item');
$sourceLines = explode("\n", (string)$source);
$resolverBody = implode("\n", array_slice($sourceLines, $resolverMethod->getStartLine() - 1, $resolverMethod->getEndLine() - $resolverMethod->getStartLine() + 1));
$check(strpos($resolverBody, "where('r.material_item_id', \$itemId)") !== false, 'resolver source checks direct product recipe item identity');
$check(strpos($resolverBody, "where('f.material_item_id', \$itemId)") !== false, 'resolver source checks direct component formula item identity');
$check(strpos($resolverBody, 'resolve_affected_products_from_material($legacyMaterialId)') !== false, 'resolver source retains legacy material mapping');

$handlerMethod = $reflection->getMethod('handle_item_change');
$handlerBody = implode("\n", array_slice($sourceLines, $handlerMethod->getStartLine() - 1, $handlerMethod->getEndLine() - $handlerMethod->getStartLine() + 1));
$check(substr_count($handlerBody, 'queue_or_rebuild_products_for_all_outlets(') === 1, 'item handler has one queue/rebuild dispatch');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: POS availability item change smoke (' . $checks . ' checks)' . PHP_EOL;
