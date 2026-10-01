<?php

declare(strict_types=1);

// DB-free inventory guard for every public endpoint in the generic Master controller.
$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Master.php';
$routesPath = $root . '/application/config/routes.php';
$source = (string) file_get_contents($controllerPath);
$routes = (string) file_get_contents($routesPath);

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

/** @return array<string,array{visibility:string,body:string}> */
function master_inventory_methods(string $source): array
{
    $tokens = token_get_all($source);
    $methods = [];
    $visibility = '';
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_PUBLIC, T_PRIVATE, T_PROTECTED], true)) {
            $visibility = strtolower(trim($token[1]));
            continue;
        }
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $name = '';
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $candidate = $tokens[$cursor];
            if (is_array($candidate) && $candidate[0] === T_STRING) {
                $name = $candidate[1];
                break;
            }
            if ($candidate === '(') {
                break;
            }
        }
        if ($name === '') {
            $visibility = '';
            continue;
        }

        while ($cursor < $count && $tokens[$cursor] !== '{') {
            $cursor++;
        }
        if ($cursor >= $count) {
            continue;
        }

        $depth = 0;
        $body = '';
        for (; $cursor < $count; $cursor++) {
            $part = $tokens[$cursor];
            $text = is_array($part) ? $part[1] : $part;
            if ($text === '{') {
                $depth++;
            }
            $body .= $text;
            if ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
        }
        $methods[$name] = [
            'visibility' => $visibility !== '' ? $visibility : 'public',
            'body' => $body,
        ];
        $visibility = '';
        $index = $cursor;
    }

    return $methods;
}

$methods = master_inventory_methods($source);
$expectedPublic = [
    '__construct',
    'index',
    'material_usage',
    'create',
    'lookup_search',
    'store',
    'edit',
    'detail',
    'update',
    'toggle',
    'stock_mode',
    'att_holiday_generate_year',
    'reorder',
];
$actualPublic = [];
foreach ($methods as $name => $method) {
    if ($method['visibility'] === 'public') {
        $actualPublic[] = $name;
    }
}
$check($actualPublic === $expectedPublic, 'public Master endpoint inventory is exact and order-stable');

$policies = [
    'index' => ["requireMasterPermission(\$entity, 'view')"],
    'material_usage' => ["requireMasterPermission('material', 'view')"],
    'create' => ["requireMasterPermission(\$entity, 'create')"],
    'lookup_search' => ["requireMasterPermission(\$entity, 'view')"],
    'store' => ["requireMasterPermission(\$entity, 'create')", 'requireMasterMutationRequest()'],
    'edit' => ["requireMasterPermission(\$entity, 'edit')"],
    'detail' => ["requireMasterPermission(\$entity, 'view')"],
    'update' => ["requireMasterPermission(\$entity, 'edit')", 'requireMasterMutationRequest()'],
    'toggle' => ["requireMasterPermission(\$entity, 'edit')", 'requireMasterMutationRequest()'],
    'stock_mode' => ["requireMasterPermission(\$entity, 'edit')", 'requireMasterMutationRequest()'],
    'att_holiday_generate_year' => ["require_permission('attendance.holiday.index', 'create')", 'requireMasterMutationRequest()'],
    'reorder' => ["requireMasterPermission(\$entity, 'edit')", 'requireMasterMutationRequest()', 'is_ajax_request()'],
];
foreach ($policies as $methodName => $needles) {
    $body = $methods[$methodName]['body'] ?? '';
    foreach ($needles as $needle) {
        $check(strpos($body, $needle) !== false, 'Master::' . $methodName . ' enforces ' . $needle);
    }
    if (in_array($methodName, ['store', 'update', 'toggle', 'stock_mode', 'att_holiday_generate_year', 'reorder'], true)) {
        $check(
            strpos($body, 'requireMasterMutationRequest()') < strpos($body, '$this->db')
                || strpos($body, '$this->db') === false,
            'Master::' . $methodName . ' checks method/CSRF before direct database mutation'
        );
    }
}

$pageCodeBody = $methods['masterPageCode']['body'] ?? '';
$entitiesBody = $methods['entities']['body'] ?? '';
preg_match_all("/^\\s{12}'([^']+)' => '/m", $pageCodeBody, $pageMatches);
preg_match_all("/^\\s{12}'([^']+)' => \\[/m", $entitiesBody, $entityMatches);
$pageEntities = $pageMatches[1] ?? [];
$configuredEntities = $entityMatches[1] ?? [];
sort($pageEntities);
sort($configuredEntities);
$legacyDenied = array_values(array_diff($configuredEntities, $pageEntities));
$orphanPolicies = array_values(array_diff($pageEntities, $configuredEntities));

$check(count($pageEntities) === 36, 'all 36 generic Master entities have a canonical page-code policy');
$check(count($configuredEntities) === 37, 'all 37 Master entity configurations are inventoried');
$check(
    strpos($pageCodeBody, "'component' => 'production.component.master.index'") !== false
        && strpos($pageCodeBody, "'component' => 'master.component.index'") === false,
    'generic component master uses the active production component page code'
);
$check($legacyDenied === ['payment-channel'], 'payment-channel is the only configured entity denied from generic Master runtime');
$check($orphanPolicies === [], 'Master page-code registry has no orphan policy');
$check(
    strpos($pageCodeBody, 'return $map[$entity] ?? null;') !== false,
    'unknown Master entity has no permission fallback'
);
$entityConfigBody = $methods['entityConfig']['body'] ?? '';
$check(
    strpos($entityConfigBody, "if (\$entity === 'payment-channel')") !== false
        && strpos($entityConfigBody, 'return null;') !== false
        && strpos($entityConfigBody, '$cfg = $all[$entity] ?? null;') !== false
        && strpos($entityConfigBody, 'if ($cfg === null) return null;') !== false,
    'legacy and unknown Master entities fail closed before generic data access'
);
$requirePermissionBody = $methods['requireMasterPermission']['body'] ?? '';
$check(
    strpos($requirePermissionBody, '$pageCode === null') !== false
        && strpos($requirePermissionBody, "show_error('Entity master tidak diizinkan.', 403") !== false
        && strpos($requirePermissionBody, 'require_permission($pageCode, $action)') !== false,
    'Master permission resolver rejects missing registry entries with 403'
);

$expectedRoutes = [
    "\$route['master/lookup-search/(:any)/(:any)'] = 'master/lookup_search/\$1/\$2';",
    "\$route['master/material/usage/(:num)']          = 'master/material_usage/\$1';",
    "\$route['master/att-holiday/generate-year']      = 'master/att_holiday_generate_year';",
    "\$route['master/(:any)']                  = 'master/index/\$1';",
    "\$route['master/(:any)/create']           = 'master/create/\$1';",
    "\$route['master/(:any)/detail/(:num)']    = 'master/detail/\$1/\$2';",
    "\$route['master/(:any)/reorder']          = 'master/reorder/\$1';",
    "\$route['master/(:any)/store']            = 'master/store/\$1';",
    "\$route['master/(:any)/edit/(:num)']      = 'master/edit/\$1/\$2';",
    "\$route['master/(:any)/update/(:num)']    = 'master/update/\$1/\$2';",
    "\$route['master/(:any)/stock-mode/(:num)'] = 'master/stock_mode/\$1/\$2';",
    "\$route['master/(:any)/toggle/(:num)']    = 'master/toggle/\$1/\$2';",
];
foreach ($expectedRoutes as $route) {
    $check(strpos($routes, $route) !== false, 'Master route remains explicit: ' . $route);
}
$specificLookupOffset = strpos($routes, "\$route['master/lookup-search/(:any)/(:any)']");
$specificUsageOffset = strpos($routes, "\$route['master/material/usage/(:num)']");
$genericOffset = strpos($routes, "\$route['master/(:any)']");
$check(
    $specificLookupOffset !== false && $specificUsageOffset !== false && $genericOffset !== false
        && $specificLookupOffset < $genericOffset && $specificUsageOffset < $genericOffset,
    'specific Master read routes precede the generic catch route'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Master endpoint registry smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'MASTER ENDPOINT REGISTRY PASS checks=' . $checks
    . ' public=' . count($actualPublic)
    . ' entities=' . count($pageEntities)
    . PHP_EOL;
