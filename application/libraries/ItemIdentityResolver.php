<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ItemIdentityResolver
{
    /** @var CI_Controller */
    protected $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
    }

    public function normalizeUsagePurpose($value): string
    {
        return strtoupper(trim((string)$value)) === 'OPERASIONAL'
            ? 'OPERASIONAL'
            : 'BAHAN_BAKU';
    }

    public function resolveTransactionIdentity(array $payload, ?string $usagePurpose = null): array
    {
        $profileKey = trim((string)($payload['profile_key'] ?? ''));
        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);

        $resolvedItemId = $this->resolveCanonicalItemId($itemId, $materialId, $profileKey);
        $resolvedMaterialId = $this->resolveCanonicalMaterialId($resolvedItemId, $materialId, $profileKey);

        return [
            'usage_purpose' => $this->normalizeUsagePurpose($usagePurpose ?? ($payload['usage_purpose'] ?? null)),
            'item_id' => $resolvedItemId,
            'material_id' => $resolvedMaterialId,
            'line_kind' => 'ITEM',
            'is_item_centric' => $resolvedItemId !== null,
        ];
    }

    public function resolveCanonicalItemId(?int $itemId, ?int $materialId, string $profileKey = ''): ?int
    {
        if ($itemId !== null && $itemId > 0) {
            return $itemId;
        }

        $profileKey = trim($profileKey);
        if ($profileKey !== '' && $this->ci->db->table_exists('mst_purchase_catalog') && $this->ci->db->field_exists('item_id', 'mst_purchase_catalog')) {
            $row = $this->ci->db
                ->select('item_id')
                ->from('mst_purchase_catalog')
                ->where('profile_key', $profileKey)
                ->where('item_id IS NOT NULL', null, false)
                ->where('item_id >', 0)
                ->order_by('is_active', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            $resolved = (int)($row['item_id'] ?? 0);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        if ($materialId !== null && $materialId > 0 && $this->ci->db->table_exists('mst_item') && $this->ci->db->field_exists('material_id', 'mst_item')) {
            $row = $this->ci->db
                ->select('id')
                ->from('mst_item')
                ->where('material_id', $materialId)
                ->where('is_active', 1)
                ->order_by('updated_at', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            $resolved = (int)($row['id'] ?? 0);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return null;
    }

    public function resolveCanonicalMaterialId(?int $itemId, ?int $materialId, string $profileKey = ''): ?int
    {
        if ($itemId !== null && $itemId > 0 && $this->ci->db->table_exists('mst_item') && $this->ci->db->field_exists('material_id', 'mst_item')) {
            $row = $this->ci->db
                ->select('material_id')
                ->from('mst_item')
                ->where('id', $itemId)
                ->limit(1)
                ->get()
                ->row_array();
            $resolved = (int)($row['material_id'] ?? 0);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        if ($materialId !== null && $materialId > 0) {
            return $materialId;
        }

        $profileKey = trim($profileKey);
        if ($profileKey !== '' && $this->ci->db->table_exists('mst_purchase_catalog') && $this->ci->db->field_exists('material_id', 'mst_purchase_catalog')) {
            $row = $this->ci->db
                ->select('material_id')
                ->from('mst_purchase_catalog')
                ->where('profile_key', $profileKey)
                ->where('material_id IS NOT NULL', null, false)
                ->where('material_id >', 0)
                ->order_by('is_active', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            $resolved = (int)($row['material_id'] ?? 0);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return null;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (int)$value;
        return $v > 0 ? $v : null;
    }
}
