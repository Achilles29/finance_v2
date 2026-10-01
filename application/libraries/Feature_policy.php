<?php
declare(strict_types=1);

/** Product boundary. Input is the bootstrap-verified signed document, never SQL/session/browser edition. */
final class Feature_policy
{
    private static ?self $runtime = null;
    private array $catalog = [];
    private array $methods = [];
    private array $pages = [];

    public function __construct(private array $manifest, private array $policy, private array $verification, private bool $customer)
    {
        if (($policy['contract'] ?? '') !== 'FINANCE_FEATURE_BOUNDARY_V1') throw new RuntimeException('FEATURE_POLICY_INVALID');
        foreach ($manifest['features'] ?? [] as $f) $this->catalog[$f['code']] = $f;
        foreach ($policy['groups'] ?? [] as $controller => $groups) {
            foreach ($groups as $features => $methods) foreach (explode(' ', $methods) as $method) {
                if (isset($this->methods[$controller][$method])) throw new RuntimeException('FEATURE_POLICY_DUPLICATE');
                $this->methods[$controller][$method] = $features === '' ? [] : explode(' ', $features);
            }
        }
        foreach ($policy['pages'] ?? [] as $controller => $methods) $this->pages[$controller] = explode(' ', $methods);
    }

    public static function runtime(): self
    {
        if (self::$runtime === null) {
            $root = dirname(__DIR__, 2);
            $context = defined('FINANCE_FEATURE_CONTEXT') ? FINANCE_FEATURE_CONTEXT : [];
            self::$runtime = new self(json_decode(file_get_contents($root.'/app-manifest.json'), true, 64, JSON_THROW_ON_ERROR),
                require $root.'/application/config/feature_access.php', $context['verification'] ?? [], !empty($context['managed']));
        }
        return self::$runtime;
    }

    public function managed(): bool { return $this->customer; }
    public function catalog(): array { return $this->catalog; }
    public function edition(): string
    {
        return $this->customer && !empty($this->verification['verified'])
            ? (string)($this->verification['payload']['edition'] ?? 'Tidak tersedia') : 'Development / legacy';
    }
    public function editionName(): string
    {
        foreach ($this->manifest['editions'] ?? [] as $edition) if ($edition['code'] === $this->edition()) return $edition['name'];
        return $this->edition();
    }
    public function allows(string $code, array $visited = []): bool
    {
        if (!$this->customer) return true;
        if (isset($visited[$code]) || ($this->catalog[$code]['value_type'] ?? '') !== 'BOOLEAN'
            || empty($this->verification['verified']) || !in_array($this->verification['status'] ?? '', ['ACTIVE','GRACE'], true)
            || ($this->verification['payload']['entitlements'][$code] ?? false) !== true) return false;
        $visited[$code] = true;
        foreach ($this->catalog[$code]['depends_on'] ?? [] as $dependency) if (!$this->allows($dependency, $visited)) return false;
        return true;
    }
    public function all(array $features): bool
    {
        foreach ($features as $feature) if (!$this->allows($feature)) return false;
        return true;
    }
    public function feature(string $code): array
    {
        return ['allowed'=>$this->allows($code),'enforced'=>$this->customer,'mode'=>$this->customer?'ENFORCE':'AUDIT_ONLY',
            'code'=>$this->allows($code)?($this->customer?'ENTITLED':'AUDIT_ALLOW'):'FEATURE_UPGRADE_REQUIRED',
            'feature_code'=>$code,'feature_name'=>$this->catalog[$code]['name'] ?? 'Fitur aplikasi',
            'edition'=>$this->edition(),'edition_name'=>$this->editionName()];
    }
    /** Canonical controller/method AFTER CodeIgniter routing; aliases cannot change the decision. */
    public function route(string $controller, string $method, array $arguments = []): array
    {
        $controller = strtolower($controller); $method = strtolower($method);
        $required = $this->methods[$controller][$method] ?? null;
        if ($required === ['@entity']) {
            $entity = $arguments[0] ?? ($method === 'index' ? 'uom' : '');
            $required = is_string($entity) && isset($this->policy['entities'][$entity]) ? [$this->policy['entities'][$entity]] : null;
        }
        $result = ['allowed'=>true,'enforced'=>$this->customer,'code'=>$this->customer?'ENTITLED':'AUDIT_ALLOW',
            'feature_code'=>'','feature_name'=>'Fitur aplikasi','edition'=>$this->edition(),'edition_name'=>$this->editionName(),
            'required'=>$required,'page'=>in_array($method, $this->pages[$controller] ?? [], true)];
        if (!$this->customer) return $result;
        if ($required === null) return array_merge($result, ['allowed'=>false,'code'=>'FEATURE_ACCESS_UNAVAILABLE']);
        foreach ($required as $code) if (!$this->allows($code)) return array_merge($result, $this->feature($code));
        return $result;
    }
    /** Display only; server decisions always use CI's canonical router above. No edition decisions cached in sidebar files. */
    public function menu(string $url, array $routes): array
    {
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        if (str_starts_with($path, 'index.php/')) $path = substr($path, 10);
        if ($path === '') $path = (string)($routes['default_controller'] ?? 'dashboard');
        foreach ($routes as $pattern => $target) {
            if (in_array($pattern, ['default_controller','404_override','translate_uri_dashes'], true)) continue;
            if (is_array($target)) $target = $target['get'] ?? $target['GET'] ?? null;
            if (!is_string($target)) continue;
            $pattern = str_replace([':any',':num'], ['[^/]+','[0-9]+'], (string)$pattern);
            if (preg_match('#^'.$pattern.'$#', $path)) { $path = preg_replace('#^'.$pattern.'$#', $target, $path); break; }
        }
        $parts = explode('/', $path);
        $controller = $parts[0]; $method = $parts[1] ?? 'index';
        if (!empty($routes['translate_uri_dashes'])) { $controller = str_replace('-','_',$controller); $method = str_replace('-','_',$method); }
        return $this->route($controller, $method, array_slice($parts,2));
    }
    /** Optional paid functions embedded in POS must not be invoked through an otherwise licensed transaction endpoint. */
    public function restrictedFields(string $entity): array
    {
        $fields = [];
        if ($entity === 'company-account' && !$this->allows('FINANCE_ADVANCED')) $fields = ['opening_balance','current_balance'];
        if ($entity === 'product' && !$this->allows('HPP_CONTROL')) $fields = ['hpp_standard','hpp_live_cache','hpp_live_at','hpp_dirty','variable_cost_mode','variable_cost_percent','hpp_live_total','hpp_live_percent_total'];
        if ($entity === 'extra' && !$this->allows('RECIPE_PRODUCT')) $fields = ['source_kind','source_product_id','source_component_id','source_material_id','source_qty','replacement_kind','replacement_product_id','replacement_component_id','replacement_material_id','replacement_qty','cost_amount'];
        return $fields;
    }

    public function payload(string $controller, string $method, array $payload, array $arguments = []): ?array
    {
        if (!$this->customer) return null;
        if (strtolower($controller) === 'master') {
            $entity = (string)($arguments[0] ?? '');
            foreach ($this->restrictedFields($entity) as $field) if (array_key_exists($field, $payload))
                return $this->feature($entity === 'company-account' ? 'FINANCE_ADVANCED' : ($entity === 'extra' ? 'RECIPE_PRODUCT' : 'HPP_CONTROL'));
        }
        if (!in_array(strtolower($controller).'/'.strtolower($method),
            ['pos/order_payment_save','pos_mobile/payment_save','pos_mobile/orders_push'], true)) return null;
        foreach ((array)($payload['orders'] ?? []) as $order) if (is_array($order)) {
            $denied = $this->payload('pos', 'order_payment_save', $order['payment'] ?? $order);
            if ($denied !== null) return $denied;
        }
        foreach (['voucher_selection','voucher_code','voucher_amount','promo_amount'] as $key) {
            $value = $payload[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== false && !(is_numeric($value) && (float)$value === 0.0)
                && !$this->allows('PROMOTION_VOUCHER')) return $this->feature('PROMOTION_VOUCHER');
        }
        foreach (['point_redeem_amount','stamp_redeem_amount'] as $key) if (!empty($payload[$key]) && !$this->allows('CUSTOMER_LOYALTY')) return $this->feature('CUSTOMER_LOYALTY');
        return null;
    }
}
