<?php

declare(strict_types=1);

/** DB/bootstrap-free source and behavior smoke for POS reversal availability. */

defined('BASEPATH') || define('BASEPATH', __DIR__);

if (!class_exists('CI_Model')) {
    class CI_Model
    {
        public $load;
        public $posavailabilityrebuildservice;
    }
}

final class PosReversalAvailabilityFakeLoader
{
    public array $calls = [];

    public function library(string $name): void
    {
        $this->calls[] = $name;
    }
}

final class PosReversalAvailabilityFakeService
{
    public array $calls = [];
    public bool $throwOnRebuild = false;

    public function mark_dirty(int $outletId, array $productIds, array $context): array
    {
        $this->calls[] = ['method' => __FUNCTION__, 'outlet_id' => $outletId, 'product_ids' => $productIds, 'context' => $context];
        return ['ok' => true, 'affected_count' => count($productIds)];
    }

    public function rebuild_products(int $outletId, array $productIds, array $context): array
    {
        $this->calls[] = ['method' => __FUNCTION__, 'outlet_id' => $outletId, 'product_ids' => $productIds, 'context' => $context];
        if ($this->throwOnRebuild) {
            throw new RuntimeException('simulated rebuild outage');
        }
        return ['ok' => true, 'success_count' => count($productIds), 'failed_count' => 0];
    }
}

if (!function_exists('log_message')) {
    function log_message($level, $message): void
    {
    }
}

require dirname(__DIR__, 2) . '/application/models/Pos_model.php';

$failures = [];
$checks = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$root = dirname(__DIR__, 2);
$modelSource = (string)file_get_contents($root . '/application/models/Pos_model.php');
$webSource = (string)file_get_contents($root . '/application/controllers/Pos.php');
$mobileSource = (string)file_get_contents($root . '/application/controllers/Pos_mobile.php');
$reflection = new ReflectionClass(Pos_model::class);
$sourceLines = explode("\n", $modelSource);
$methodBody = static function (ReflectionClass $class, array $lines, string $name): string {
    $method = $class->getMethod($name);
    return implode("\n", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
};

foreach (['save_order_void', 'save_order_refund'] as $name) {
    $body = $methodBody($reflection, $sourceLines, $name);
    $commit = strpos($body, '$this->db->trans_commit();');
    $refresh = strpos($body, '$this->refresh_pos_reversal_availability_after_commit(');
    $catch = strpos($body, '} catch (Throwable $e) {');
    $check($commit !== false && $refresh !== false && $commit < $refresh, $name . ' refreshes only after commit');
    $check($catch !== false && $catch < $refresh, $name . ' refresh is outside rollback catch');
    $check(substr_count($body, 'refresh_pos_reversal_availability_after_commit(') === 1, $name . ' dispatches refresh exactly once');
    $check(strpos($body, "'availability_rebuild' => \$availabilityRebuild") !== false && strpos($body, "'warning' => \$availabilityRebuild['warning']") !== false, $name . ' adds rebuild and warning metadata');
}

$helper = $reflection->getMethod('refresh_pos_reversal_availability_after_commit');
$helper->setAccessible(true);
$model = $reflection->newInstanceWithoutConstructor();
$model->load = new PosReversalAvailabilityFakeLoader();
$model->posavailabilityrebuildservice = new PosReversalAvailabilityFakeService();
$order = ['header' => ['outlet_id' => 17], 'lines' => [['product_id' => 3], ['product_id' => 3], ['product_id' => 9]]];
$result = $helper->invoke($model, $order, 23, 'ORDER_VOID', 71);
$check(($result['ok'] ?? false) === true && ($result['success_count'] ?? 0) === 2, 'helper rebuilds unique order products successfully');
$check(array_column($model->posavailabilityrebuildservice->calls, 'method') === ['mark_dirty', 'rebuild_products'], 'helper marks dirty then rebuilds exactly once');
$context = $model->posavailabilityrebuildservice->calls[1]['context'] ?? [];
$check(($context['event_source'] ?? '') === 'ORDER_VOID' && ($context['event_id'] ?? 0) === 71 && ($context['actor_employee_id'] ?? 0) === 23, 'helper forwards actor and event metadata');

$model->posavailabilityrebuildservice->throwOnRebuild = true;
$failure = $helper->invoke($model, $order, 23, 'ORDER_REFUND', 72);
$check(($failure['ok'] ?? true) === false && strpos((string)($failure['warning'] ?? ''), 'sudah tersimpan') !== false, 'rebuild exception is an additive committed-transaction warning');

// Controller is intentionally inspected by method boundaries without loading CI.
$extractControllerMethod = static function (string $source, string $name): string {
    $start = strpos($source, 'public function ' . $name . '(');
    $next = $start === false ? false : strpos($source, "\n    public function ", $start + 1);
    return $start === false ? '' : substr($source, $start, $next === false ? null : $next - $start);
};
foreach (['order_void_save', 'order_refund_save'] as $name) {
    $body = $extractControllerMethod($webSource, $name);
    $check(strpos($body, "\$result['availability_rebuild']") !== false, 'web ' . $name . ' consumes model rebuild metadata');
    $check(strpos($body, 'trigger_stock_live_refresh_for_order(') === false, 'web ' . $name . ' does not duplicate rebuild');
    $check(strpos($body, "'success_count'") !== false && strpos($body, "'failed_count'") !== false, 'web ' . $name . ' preserves count response shape');
}
$check(substr_count($mobileSource, '$this->Pos_model->save_order_void(') >= 1 && substr_count($mobileSource, '$this->Pos_model->save_order_refund(') >= 1, 'mobile benefits through shared model writers without controller changes');
$check(strpos($webSource, 'private function trigger_stock_live_refresh_for_order(') !== false, 'shared web helper remains available for other caller');

if ($failures !== []) {
    foreach ($failures as $failureMessage) {
        fwrite(STDERR, 'FAIL: ' . $failureMessage . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: POS reversal availability smoke (' . $checks . ' checks)' . PHP_EOL;
