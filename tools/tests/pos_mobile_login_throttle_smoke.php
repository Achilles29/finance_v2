<?php

declare(strict_types=1);

/**
 * Regression contract: POS Mobile login must use the same account/IP throttle
 * boundary as web login. Source-only: no credential, request, or DB access.
 */

$root = dirname(__DIR__, 2);
$controller = (string)@file_get_contents($root . '/application/controllers/Pos_mobile.php');
$authModel = (string)@file_get_contents($root . '/application/models/Auth_model.php');
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

$method = static function (string $source, string $name): string {
    $start = strpos($source, 'public function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        return '';
    }
    $depth = 0;
    for ($index = $brace, $length = strlen($source); $index < $length; $index++) {
        if ($source[$index] === '{') {
            $depth++;
        } elseif ($source[$index] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $index - $start + 1);
            }
        }
    }
    return '';
};

$login = $method($controller, 'login');
$check($login !== '' && $authModel !== '', 'POS Mobile login and authentication model sources are readable');
$check(
    strpos($login, 'require_mobile_post()') !== false
        && strpos($login, '$this->request_payload()') !== false,
    'POS Mobile login remains a POST-only JSON boundary'
);
$check(
    strpos($login, '$this->Auth_model->attempt_login(') !== false
        && strpos($login, '(string)$this->input->ip_address()') !== false,
    'POS Mobile passes the request IP into the shared login throttle'
);
$check(
    strpos($login, '$this->Auth_model->attempt_login(') < strpos($login, '$this->unique_active_mobile_terminal(')
        && strpos($login, '$this->unique_active_mobile_terminal(') < strpos($login, '$this->db->insert(\'pos_mobile_auth_token\'') ,
    'credential validation and throttle run before terminal lookup or token issuance'
);
$check(
    substr_count($login, "'Kredensial atau perangkat tidak valid.'") === 2,
    'invalid credential and terminal responses remain indistinguishable'
);
$check(
    strpos($authModel, '$isWebLogin = $ip_address !== null;') !== false
        && strpos($authModel, 'count_recent_ip_login_failures($ipAddress)') !== false
        && strpos($authModel, 'record_login_failure(') !== false,
    'shared authentication model has account/IP failure accounting for an IP-bearing login'
);

if ($failures !== []) {
    fwrite(STDERR, 'FAIL pos-mobile-login-throttle checks=' . count($failures) . PHP_EOL);
    exit(1);
}

echo 'PASS pos-mobile-login-throttle checks=' . $checks . PHP_EOL;
