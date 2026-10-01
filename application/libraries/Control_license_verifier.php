<?php

/** Finance reader for Control's NAMUA_LICENSE_V1 envelope, without network or database writes. */
final class Control_license_verifier
{
    public static function verify(array $envelope, array $trust, array $identity, ?int $now = null): array
    {
        try {
            if (!function_exists('sodium_crypto_sign_verify_detached')) throw new RuntimeException('SODIUM_UNAVAILABLE');
            if (($envelope['schema'] ?? null) !== 1 || ($envelope['algorithm'] ?? null) !== 'Ed25519') throw new RuntimeException('ENVELOPE_INVALID');
            if (($trust['schema'] ?? null) !== 1 || ($trust['purpose'] ?? '') !== 'NAMUA_LICENSE_SIGNING'
                || ($trust['algorithm'] ?? '') !== 'Ed25519' || ($trust['status'] ?? '') !== 'ACTIVE'
                || ($trust['product_code'] ?? '') !== 'NAMUA_FINANCE') throw new RuntimeException('TRUST_INVALID');
            $keyId = $envelope['key_id'] ?? null;
            if (!is_string($keyId) || $keyId === '' || !is_string($trust['key_id'] ?? null) || !hash_equals($trust['key_id'], $keyId)) throw new RuntimeException('KEY_NOT_TRUSTED');
            $public = self::decode($trust['public_key_base64'] ?? null, 32);
            if (!is_string($trust['public_key_sha256'] ?? null) || !hash_equals($trust['public_key_sha256'], hash('sha256', $public))) throw new RuntimeException('TRUST_FINGERPRINT_INVALID');
            $signature = self::decode($envelope['signature_base64'] ?? null, 64);
            $encoded = $envelope['payload_base64'] ?? null;
            if (!is_string($encoded) || strlen($encoded) > 262144) throw new RuntimeException('PAYLOAD_INVALID');
            $raw = base64_decode($encoded, true);
            if (!is_string($raw) || $raw === '' || base64_encode($raw) !== $encoded) throw new RuntimeException('PAYLOAD_INVALID');
            // Verify the original bytes. Never re-encode JSON before checking Control's signature.
            if (!sodium_crypto_sign_verify_detached($signature, "NAMUA_LICENSE_V1\n" . hash('sha256', $raw), $public)) throw new RuntimeException('SIGNATURE_INVALID');
            $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || ($payload['schema'] ?? null) !== 1 || ($payload['product'] ?? '') !== 'NAMUA_FINANCE'
                || ($payload['key_id'] ?? null) !== $keyId || ($payload['metric'] ?? '') !== 'SERVER_INSTANCE'
                || !in_array($payload['rights_model'] ?? '', ['PERPETUAL', 'TERM'], true)
                || !is_array($payload['entitlements'] ?? null) || !is_string($payload['license_id'] ?? null) || $payload['license_id'] === ''
                || !is_string($payload['edition'] ?? null) || $payload['edition'] === '') throw new RuntimeException('PAYLOAD_CONTRACT_INVALID');
            foreach (['instance_id', 'installation_id', 'instance_public_key_sha256'] as $field) {
                if (!is_string($identity[$field] ?? null) || $identity[$field] === '' || !is_string($payload[$field] ?? null)
                    || !hash_equals($identity[$field], $payload[$field])) throw new RuntimeException('INSTALLATION_MISMATCH');
            }
            if (preg_match('/\A[a-f0-9]{64}\z/D', $identity['instance_public_key_sha256']) !== 1) throw new RuntimeException('INSTALLATION_MISMATCH');
            // Older deployments keep their identity triple. Managed customer installs additionally
            // require the Control-signed host fingerprint, never an unsigned cache hint.
            if (array_key_exists('machine_fingerprint_sha256', $identity)) {
                if (!is_string($identity['machine_fingerprint_sha256'])
                    || preg_match('/\A[a-f0-9]{64}\z/D', $identity['machine_fingerprint_sha256']) !== 1
                    || !is_string($payload['machine_fingerprint_sha256'] ?? null)
                    || !hash_equals($identity['machine_fingerprint_sha256'], $payload['machine_fingerprint_sha256'])) {
                    throw new RuntimeException('MACHINE_BINDING_MISMATCH');
                }
            }
            $issued = self::timestamp($payload['issued_at'] ?? null);
            $expires = self::timestamp($payload['expires_at'] ?? null);
            $grace = self::timestamp($payload['grace_until'] ?? null);
            $now = $now ?? time();
            if ($issued > $now + 300 || $expires < $issued || $grace < $expires) throw new RuntimeException('LICENSE_TIME_INVALID');
            $maintenance = $payload['maintenance_ends_at'] ?? null;
            if ($maintenance !== null) self::timestamp($maintenance);
            if (($payload['rights_model'] ?? '') === 'PERPETUAL' && ($payload['perpetual_granted_at'] ?? null) !== null) self::timestamp($payload['perpetual_granted_at']);
            foreach ($payload['entitlements'] as $code => $value) {
                if (!is_string($code) || preg_match('/\A[A-Z][A-Z0-9_]{1,99}\z/D', $code) !== 1
                    || (!is_bool($value) && !is_int($value) && !is_string($value))) throw new RuntimeException('ENTITLEMENT_INVALID');
            }
            return ['verified' => true, 'status' => $now <= $expires ? 'ACTIVE' : ($now <= $grace ? 'GRACE' : 'RESTRICTED'),
                'code' => 'SIGNATURE_VERIFIED', 'payload' => $payload, 'payload_sha256' => hash('sha256', $raw)];
        } catch (Throwable $error) {
            return ['verified' => false, 'status' => 'RESTRICTED', 'code' => $error instanceof RuntimeException ? $error->getMessage() : 'DOCUMENT_INVALID'];
        }
    }

    private static function decode($value, int $length): string
    {
        if (!is_string($value) || strlen($value) > 256) throw new RuntimeException('ENCODING_INVALID');
        $decoded = base64_decode($value, true);
        if (!is_string($decoded) || strlen($decoded) !== $length || base64_encode($decoded) !== $value) throw new RuntimeException('ENCODING_INVALID');
        return $decoded;
    }

    private static function timestamp($value): int
    {
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/D', $value) !== 1) throw new RuntimeException('LICENSE_TIME_INVALID');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) throw new RuntimeException('LICENSE_TIME_INVALID');
        return $date->getTimestamp();
    }

    /** Trust and installation identity are deployment-owned, never editable through SQL/UI. */
    public static function deployment_document(string $path, string $webroot, int $maxBytes = 16384): array
    {
        require_once __DIR__.'/CustomerPlatform.php';
        $portableRoot=CustomerPlatform::root($webroot);
        if (CustomerPlatform::portable($portableRoot)) {
            // Exact read-only license documents, not a blanket exception for source-tree JSON.
            $allowed=['storage/customer-installation.json','storage/license/identity.json',
                'storage/license/trust.json','storage/license/runtime.json'];
            if (!in_array(str_replace('\\','/',$path),array_map(static fn($p)=>$portableRoot.'/'.$p,$allowed),true)
                || $maxBytes<1 || $maxBytes>300000) return [];
            try { return CustomerPlatform::document($portableRoot,$path,$maxBytes); } catch (Throwable $e) { return []; }
        }
        $real = $path !== '' ? realpath($path) : false;
        $root = realpath($webroot);
        if ($real === false || $real !== $path || $root === false || is_link($path) || !is_file($real) || !is_readable($real)
            || strpos(str_replace('\\', '/', $real) . '/', rtrim(str_replace('\\', '/', $root), '/') . '/') === 0
            || $maxBytes < 1 || $maxBytes > 300000 || filesize($real) > $maxBytes) return [];
        if (PHP_OS_FAMILY !== 'Windows') {
            $stat = stat($real);
            // Root-owned, no group/world writes. Runtime writers cannot replace the parent either.
            if (!is_array($stat) || (int)$stat['uid'] !== 0 || ($stat['mode'] & 0022) !== 0) return [];
            $parent = dirname($real);
            while ($parent !== dirname($parent)) {
                $parentStat = stat($parent);
                if (!is_array($parentStat) || (int)$parentStat['uid'] !== 0 || ($parentStat['mode'] & 0022) !== 0) return [];
                $parent = dirname($parent);
            }
        } else {
            // Windows ACL provisioning has not been accepted; don't silently trust editable local files.
            return [];
        }
        $json = json_decode((string)file_get_contents($real), true);
        return is_array($json) ? $json : [];
    }
}
