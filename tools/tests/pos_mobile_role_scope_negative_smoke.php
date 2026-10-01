<?php

declare(strict_types=1);

defined('POS_MOBILE_AUTHORIZATION_HARNESS_ONLY') || define('POS_MOBILE_AUTHORIZATION_HARNESS_ONLY', true);
require __DIR__ . '/pos_mobile_authorization_smoke.php';

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

$permissions = [
    'pos.cashier.index' => [
        'can_view' => 1,
        'can_create' => 1,
        'can_edit' => 1,
        'can_delete' => 0,
        'can_export' => 0,
    ],
];
$usernameKey = 'user' . 'name';
$loginUser = [
    'id' => 42,
    'employee_id' => 314,
    $usernameKey => 'scope-smoke',
    'email' => 'scope-smoke@example.test',
];
$deviceKey = 'scope-device-key';
$terminalRows = [[
    'id' => 501,
    'outlet_id' => 71,
    'device_key' => $deviceKey,
    'is_active' => 1,
]];
$passwordKey = 'pass' . 'word';
$loginPayload = [
    'identifier' => 'scope-smoke',
    $passwordKey => 'fixture-only-value',
    'terminal_device_key' => $deviceKey,
];

foreach (['NONE', 'AMBIGUOUS'] as $invalidState) {
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        $permissions,
        true,
        0,
        $loginPayload,
        [],
        [],
        'POST',
        $terminalRows,
        $loginUser,
        [],
        [],
        ['state' => $invalidState, 'division_id' => null]
    );
    $controller->login();
    $response = pos_mobile_smoke_json($output);
    $check($output->status === 403, 'mobile login rejects ' . $invalidState . ' role scope');
    $check($db->tokenInserts === 0, 'mobile login does not issue a token for ' . $invalidState . ' role scope');
    $check($auth->scopeCalls === 1, 'mobile login resolves current role scope for ' . $invalidState);
    $check(strpos((string)($response['message'] ?? ''), 'scope akses akun') !== false, 'mobile login returns bounded scope guidance for ' . $invalidState);
}

[$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
    $permissions,
    true,
    0,
    $loginPayload,
    [],
    [],
    'POST',
    $terminalRows,
    $loginUser,
    [],
    [],
    ['state' => 'SINGLE', 'division_id' => 7]
);
$controller->login();
$response = pos_mobile_smoke_json($output);
$access = (array)($response['access_context'] ?? []);
$check($output->status === 200 && $db->tokenInserts === 1, 'mobile login accepts a valid SINGLE scope');
$check(
    ($access['division_scope_state'] ?? '') === 'SINGLE'
        && (int)($access['division_id'] ?? 0) === 7
        && (int)($access['outlet_id'] ?? 0) === 71
        && (int)($access['terminal_id'] ?? 0) === 501,
    'mobile login returns authoritative division, outlet, and terminal context'
);

$token = 'scope-bearer-token';
$tokenRows = [[
    'id' => 41,
    'token_hash' => hash('sha256', $token),
    'user_id' => 42,
    'employee_id' => 314,
    'terminal_device_key' => $deviceKey,
    'revoked_at' => null,
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
    'is_active' => 1,
]];
$headers = [
    'Authorization' => 'Bearer ' . $token,
    'X-Pos-Mobile-Device-Key' => $deviceKey,
];

[$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
    $permissions,
    true,
    0,
    [],
    $headers,
    $tokenRows,
    'POST',
    $terminalRows,
    null,
    [],
    [],
    ['state' => 'AMBIGUOUS', 'division_id' => null]
);
$authorized = pos_mobile_smoke_invoke($controller, 'authorize_mobile', [true]);
$mobileUser = (new ReflectionProperty($controller, 'mobileUser'));
$mobileUser->setAccessible(true);
$check($authorized === false && $output->status === 403, 'existing bearer is rejected after role scope becomes ambiguous');
$check($db->tokenLastSeenUpdates === 0, 'rejected ambiguous bearer does not refresh token last_seen');
$check($mobileUser->getValue($controller) === null, 'rejected ambiguous bearer never becomes request identity');
$check($auth->loadCalls === 1 && $auth->scopeCalls === 1, 'bearer re-resolves live permission and scope exactly once');

[$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
    $permissions,
    true,
    0,
    [],
    $headers,
    $tokenRows,
    'POST',
    $terminalRows,
    null,
    [],
    [],
    ['state' => 'SINGLE', 'division_id' => 7]
);
$authorized = pos_mobile_smoke_invoke($controller, 'authorize_mobile', [true]);
$access = (array)pos_mobile_smoke_invoke($controller, 'mobile_access_context_payload');
$check($authorized === true && $db->tokenLastSeenUpdates === 1, 'valid scoped bearer authenticates and refreshes last_seen');
$check(
    ($access['division_scope_state'] ?? '') === 'SINGLE'
        && (int)($access['division_id'] ?? 0) === 7
        && (int)($access['outlet_id'] ?? 0) === 71
        && (int)($access['terminal_id'] ?? 0) === 501,
    'valid bearer request identity contains authoritative scope and device binding'
);

[$controller, $auth, $output] = pos_mobile_smoke_controller(
    $permissions,
    true,
    42,
    [],
    [],
    [],
    'POST',
    [],
    null,
    [],
    [],
    ['state' => 'NONE', 'division_id' => null]
);
$authorized = pos_mobile_smoke_invoke($controller, 'authorize_mobile', [true]);
$check($authorized === false && $output->status === 403, 'session-backed mobile call also rejects unresolved role scope');
$check($auth->loadCalls === 1 && $auth->scopeCalls === 1, 'session-backed mobile call resolves live permission and scope');

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Pos_mobile.php');
$loginStart = strpos($source, 'public function login()');
$loginEnd = strpos($source, 'public function logout()', (int)$loginStart);
$loginBody = substr($source, (int)$loginStart, (int)$loginEnd - (int)$loginStart);
$check(
    strpos($loginBody, 'mobile_division_scope_context(') < strpos($loginBody, "insert('pos_mobile_auth_token'"),
    'login validates live division scope before token issuance'
);
$tokenStart = strpos($source, 'private function load_mobile_user_from_token(');
$tokenEnd = strpos($source, 'private function unique_active_mobile_terminal(', (int)$tokenStart);
$tokenBody = substr($source, (int)$tokenStart, (int)$tokenEnd - (int)$tokenStart);
$check(
    strpos($tokenBody, 'mobile_division_scope_context(') < strpos($tokenBody, '$this->mobileUser = $row'),
    'bearer validates live division scope before establishing request identity'
);

if ($failures !== []) {
    fwrite(STDERR, 'POS MOBILE ROLE SCOPE NEGATIVE FAIL checks=' . $checks . ' failures=' . count($failures) . PHP_EOL);
    exit(1);
}

echo 'POS MOBILE ROLE SCOPE NEGATIVE PASS checks=' . $checks . PHP_EOL;
