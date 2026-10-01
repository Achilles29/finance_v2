<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/application/libraries/Control_license_verifier.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++; echo "PASS {$label}\n";
};
$pair = sodium_crypto_sign_keypair();
$private = sodium_crypto_sign_secretkey($pair); $public = sodium_crypto_sign_publickey($pair);
$now = 1800000000;
$identity = ['instance_id' => 'fixture-instance', 'installation_id' => 'fixture-installation', 'instance_public_key_sha256' => hash('sha256', 'fixture-device')];
$trust = ['schema' => 1, 'purpose' => 'NAMUA_LICENSE_SIGNING', 'algorithm' => 'Ed25519', 'status' => 'ACTIVE', 'product_code' => 'NAMUA_FINANCE',
    'key_id' => 'fixture-key', 'public_key_base64' => base64_encode($public), 'public_key_sha256' => hash('sha256', $public)];
$payload = $identity + ['schema' => 1, 'license_id' => 'fixture-license', 'serial' => 'fixture-serial', 'product' => 'NAMUA_FINANCE',
    'edition' => 'STARTER_POS', 'metric' => 'SERVER_INSTANCE', 'rights_model' => 'PERPETUAL', 'entitlements' => ['POS_CORE' => true, 'PAYROLL' => false],
    'issued_at' => gmdate(DATE_ATOM, $now - 30), 'expires_at' => gmdate(DATE_ATOM, $now + 100), 'grace_until' => gmdate(DATE_ATOM, $now + 200),
    'maintenance_ends_at' => gmdate(DATE_ATOM, $now - 3600), 'perpetual_granted_at' => gmdate(DATE_ATOM, $now - 7200), 'key_id' => 'fixture-key'];
// Exact envelope and signing context from Control/tools/process_license_issuance.php.
$sign = static function (array $payload) use ($private): array {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return ['schema' => 1, 'algorithm' => 'Ed25519', 'key_id' => 'fixture-key', 'payload_base64' => base64_encode($json),
        'signature_base64' => base64_encode(sodium_crypto_sign_detached("NAMUA_LICENSE_V1\n" . hash('sha256', $json), $private))];
};
$envelope = $sign($payload);
$verify = static fn(array $envelope, array $identityOverride = []): array => Control_license_verifier::verify($envelope, $trust, $identityOverride ?: $identity, $now);
$active = $verify($envelope);
$check($active['verified'] && $active['status'] === 'ACTIVE', 'Control-compatible signed perpetual license remains active after maintenance expiry');
$check(Control_license_verifier::verify($envelope, $trust, $identity, $now + 100)['status'] === 'ACTIVE', 'lease end is inclusive');
$check(Control_license_verifier::verify($envelope, $trust, $identity, $now + 101)['status'] === 'GRACE', 'offline grace recognized');
$check(Control_license_verifier::verify($envelope, $trust, $identity, $now + 200)['status'] === 'GRACE', 'grace end is inclusive');
$check(Control_license_verifier::verify($envelope, $trust, $identity, $now + 201)['status'] === 'RESTRICTED', 'expired lease is not confused with maintenance');
$bad = $envelope; $bad['signature_base64'] = base64_encode(random_bytes(64));
$check(!$verify($bad)['verified'], 'modified signature rejected');
$bad = $envelope; $bad['payload_base64'] = base64_encode(json_encode(array_merge($payload, ['edition' => 'ENTERPRISE'])));
$check(!$verify($bad)['verified'], 'modified local edition rejected');
$bad = $envelope; $bad['algorithm'] = 'none'; $check(!$verify($bad)['verified'], 'algorithm downgrade rejected');
$bad = $envelope; $bad['key_id'] = 'other-key'; $check(!$verify($bad)['verified'], 'untrusted key rejected');
foreach (['instance_id', 'installation_id', 'instance_public_key_sha256'] as $field) {
    $changed = $identity; $changed[$field] = 'another-installation';
    $check(!$verify($envelope, $changed)['verified'], 'binding checked: ' . $field);
}
foreach (['product' => 'ANOTHER_PRODUCT', 'schema' => 2, 'rights_model' => 'FREE', 'issued_at' => gmdate(DATE_ATOM, $now + 301), 'grace_until' => gmdate(DATE_ATOM, $now), 'expires_at' => '2026-02-31T00:00:00+00:00', 'entitlements' => ['POS_CORE' => []]] as $key => $value) {
    $changed = $payload; $changed[$key] = $value;
    $check(!$verify($sign($changed))['verified'], 'signed invalid claim rejected: ' . $key);
}
$badTrust = $trust; $badTrust['status'] = 'REVOKED';
$check(!Control_license_verifier::verify($envelope, $badTrust, $identity, $now)['verified'], 'revoked trust rejected');
$bad = $envelope; $bad['payload_base64'] = str_repeat('A', 262145); $check(!$verify($bad)['verified'], 'oversize payload rejected');
$check(Control_license_verifier::deployment_document(__FILE__, dirname(__DIR__, 2)) === [], 'webroot trust rejected');

define('BASEPATH', __DIR__); class CI_Model {}
require dirname(__DIR__, 2) . '/application/models/License_runtime_model.php';
class LicenseFixture extends License_runtime_model {
    public array $result = [];
    public function current_license(): array { return ['id' => 1, 'verification_status' => 'VERIFIED']; }
    public function verification(): array { return $this->result; }
}
$model = new LicenseFixture();
$model->result = ['verified' => false, 'status' => 'ACTIVE', 'payload' => $payload];
$check(!$model->feature_enabled('POS_CORE'), 'forged database VERIFIED flag cannot grant access');
$model->result = $active;
$check($model->feature_enabled('POS_CORE') && !$model->feature_enabled('PAYROLL') && !$model->feature_enabled('UNKNOWN'), 'runtime reads signed entitlements, not mutable feature rows');
$model->result['payload']['entitlements']['POS_CORE'] = 'true';
$check(!$model->feature_enabled('POS_CORE'), 'string true is not a boolean grant');
$model->result = $active; $model->result['status'] = 'RESTRICTED';
$check(!$model->feature_enabled('POS_CORE'), 'out-of-lease grants rejected at entitlement boundary');
sodium_memzero($private); sodium_memzero($pair);
echo "All {$checks} Control license verification checks passed.\n";
