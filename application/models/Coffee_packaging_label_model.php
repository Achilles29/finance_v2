<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Coffee_packaging_label_model extends CI_Model
{
    private const TABLE = 'coffee_packaging_label';
    private const TEMPLATE_TABLE = 'coffee_packaging_label_template';

    public function table_ready(): bool
    {
        return $this->db->table_exists(self::TABLE);
    }

    public function template_table_ready(): bool
    {
        return $this->db->table_exists(self::TEMPLATE_TABLE);
    }

    /**
     * System templates are also returned before the migration is applied, so
     * the editor remains readable during a rolling deployment. Saving a custom
     * template still requires the dedicated table.
     */
    public function list_templates(bool $includeInactive = false): array
    {
        if (!$this->template_table_ready()) {
            return $this->fallback_templates();
        }

        $this->db
            ->from(self::TEMPLATE_TABLE)
            // The default system template must remain the first option even
            // when an operator adds templates whose names sort earlier.
            ->order_by("CASE WHEN template_key = 'classic-portrait' THEN 0 WHEN template_key = 'retail-wide' THEN 1 ELSE 2 END", '', false)
            ->order_by('is_system', 'DESC')
            ->order_by('template_name', 'ASC');
        if (!$includeInactive) {
            $this->db->where('is_active', 1);
        }

        return $this->db->get()->result_array();
    }

    public function find_template(int $id, bool $includeInactive = false): ?array
    {
        if ($id <= 0 || !$this->template_table_ready()) {
            return null;
        }

        $this->db->from(self::TEMPLATE_TABLE)->where('id', $id);
        if (!$includeInactive) {
            $this->db->where('is_active', 1);
        }

        $row = $this->db->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function find_template_by_key(string $key, bool $includeInactive = false): ?array
    {
        $key = trim($key);
        if ($key === '' || !$this->template_table_ready()) {
            return null;
        }

        $this->db->from(self::TEMPLATE_TABLE)->where('template_key', $key);
        if (!$includeInactive) {
            $this->db->where('is_active', 1);
        }

        $row = $this->db->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function save_template(array $data, int $id = 0): int
    {
        if (!$this->template_table_ready()) {
            return 0;
        }

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update(self::TEMPLATE_TABLE, $data);
            return $id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert(self::TEMPLATE_TABLE, $data);
        return (int)$this->db->insert_id();
    }

    public function delete_custom_template(int $id, ?int $userId = null): bool
    {
        if ($id <= 0 || !$this->template_table_ready()) {
            return false;
        }

        $data = [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($userId !== null && $this->db->field_exists('updated_by', self::TEMPLATE_TABLE)) {
            $data['updated_by'] = $userId;
        }

        return (bool)$this->db
            ->where('id', $id)
            ->where('is_system', 0)
            ->update(self::TEMPLATE_TABLE, $data);
    }

    public function next_template_key(string $templateName): string
    {
        $base = strtolower(trim($templateName));
        $base = preg_replace('/[^a-z0-9]+/i', '-', $base);
        $base = trim((string)$base, '-');
        $base = substr($base !== '' ? $base : 'template-label', 0, 55);
        $candidate = 'custom-' . $base;
        if (!$this->template_table_ready()) {
            return $candidate;
        }

        for ($suffix = 1; $suffix <= 99; $suffix++) {
            $key = $suffix === 1 ? $candidate : $candidate . '-' . $suffix;
            if (!$this->find_template_by_key($key, true)) {
                return $key;
            }
        }

        return $candidate . '-' . date('His');
    }

    public function list_labels(array $filters = [], int $limit = 80): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));

        $hasLabelName = $this->db->field_exists('label_name', self::TABLE);
        $hasProductId = $this->db->field_exists('product_id', self::TABLE);
        $select = 'id, label_code, coffee_name, origin, weight_text, roast_level, process_method, image_path, theme_preset, design_json, is_active, updated_at, created_at';
        $select .= $hasLabelName ? ', label_name' : ', coffee_name AS label_name';
        if ($hasProductId) {
            $select .= ', product_id';
        }
        if ($this->db->field_exists('logo_path', self::TABLE)) {
            $select .= ', logo_path';
        }
        foreach (['body_level', 'elevation_text', 'bean_type', 'footer_note'] as $optionalField) {
            if ($this->db->field_exists($optionalField, self::TABLE)) {
                $select .= ', ' . $optionalField;
            }
        }

        $this->db
            ->select($select)
            ->from(self::TABLE);

        if ($q !== '') {
            $this->db
                ->group_start()
                ->like($hasLabelName ? 'label_name' : 'coffee_name', $q);
            if ($hasLabelName) {
                $this->db->or_like('coffee_name', $q);
            }
            $this->db
                ->or_like('origin', $q)
                ->or_like('label_code', $q)
                ->or_like('tasting_notes', $q)
                ->group_end();
        }

        if ($status === 'INACTIVE') {
            $this->db->where('is_active', 0);
        } elseif ($status === 'ALL') {
            // no-op
        } else {
            $this->db->where('is_active', 1);
        }

        return $this->db
            ->order_by('updated_at IS NULL', 'ASC', false)
            ->order_by('updated_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->result_array();
    }

    public function find(int $id): ?array
    {
        if ($id <= 0 || !$this->table_ready()) {
            return null;
        }

        $row = $this->db
            ->from(self::TABLE)
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function save(array $data, int $id = 0): int
    {
        if (!$this->table_ready()) {
            return 0;
        }

        if (!$this->db->field_exists('logo_path', self::TABLE)) {
            unset($data['logo_path']);
        }
        foreach (['label_name', 'product_id', 'body_level', 'elevation_text', 'bean_type', 'footer_note'] as $optionalField) {
            if (!$this->db->field_exists($optionalField, self::TABLE)) {
                unset($data[$optionalField]);
            }
        }

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update(self::TABLE, $data);
            return $id;
        }

        $data['label_code'] = $data['label_code'] ?? $this->next_label_code();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert(self::TABLE, $data);
        return (int)$this->db->insert_id();
    }

    public function set_active(int $id, bool $active, ?int $userId = null): bool
    {
        if ($id <= 0 || !$this->table_ready()) {
            return false;
        }

        $data = [
            'is_active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($userId !== null && $this->db->field_exists('updated_by', self::TABLE)) {
            $data['updated_by'] = $userId;
        }

        return (bool)$this->db->where('id', $id)->update(self::TABLE, $data);
    }

    public function count_by_image_path(string $imagePath, int $excludeId = 0): int
    {
        $imagePath = trim($imagePath);
        if ($imagePath === '' || !$this->table_ready()) {
            return 0;
        }

        $this->db
            ->from(self::TABLE)
            ->where('image_path', $imagePath);
        if ($excludeId > 0) {
            $this->db->where('id <>', $excludeId);
        }

        return (int)$this->db->count_all_results();
    }

    public function image_usage_map(): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $hasLabelName = $this->db->field_exists('label_name', self::TABLE);
        $select = 'image_path, coffee_name, origin, process_method, updated_at';
        if ($hasLabelName) {
            $select .= ', label_name';
        }

        $rows = $this->db
            ->select($select)
            ->from(self::TABLE)
            ->where('image_path IS NOT NULL', null, false)
            ->where("TRIM(COALESCE(image_path, '')) <> ''", null, false)
            ->order_by('updated_at', 'DESC')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $path = trim((string)($row['image_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            if (!isset($map[$path])) {
                $map[$path] = [
                    'count' => 0,
                    'display_name' => trim((string)($row['label_name'] ?? $row['coffee_name'] ?? '')),
                    'origin' => trim((string)($row['origin'] ?? '')),
                    'process_method' => trim((string)($row['process_method'] ?? '')),
                ];
            }
            $map[$path]['count']++;
        }

        return $map;
    }

    public function roastery_product_options(int $limit = 300): array
    {
        if (!$this->db->table_exists('mst_product') || !$this->db->table_exists('mst_product_division')) {
            return [];
        }

        $select = 'p.id, p.product_code, p.product_name, p.selling_price, d.code AS division_code, d.name AS division_name';

        return $this->db
            ->select($select)
            ->from('mst_product p')
            ->join('mst_product_division d', 'd.id = p.product_division_id', 'inner')
            ->where('d.code', 'ROASTERY')
            ->where('p.is_active', 1)
            ->order_by('p.product_name', 'ASC')
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->result_array();
    }

    public function find_roastery_product(int $productId): ?array
    {
        if ($productId <= 0 || !$this->db->table_exists('mst_product') || !$this->db->table_exists('mst_product_division')) {
            return null;
        }

        $row = $this->db
            ->select('p.id, p.product_code, p.product_name')
            ->from('mst_product p')
            ->join('mst_product_division d', 'd.id = p.product_division_id', 'inner')
            ->where('p.id', $productId)
            ->where('d.code', 'ROASTERY')
            ->where('p.is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function next_label_code(): string
    {
        $prefix = 'LBL-' . date('Ymd') . '-';
        $row = $this->db
            ->select('label_code')
            ->from(self::TABLE)
            ->like('label_code', $prefix, 'after')
            ->order_by('label_code', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $last = 0;
        if (!empty($row['label_code']) && preg_match('/-(\d+)$/', (string)$row['label_code'], $m)) {
            $last = (int)$m[1];
        }

        return $prefix . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function fallback_templates(): array
    {
        return [
            [
                'id' => 0,
                'template_key' => 'classic-portrait',
                'template_name' => 'Classic Portrait',
                'description' => 'Template awal model 1: label tegak 90 x 140 mm.',
                'design_json' => '{"schema":"roastery-label-template-v1","canvas":{"width":90,"height":140,"theme":"heritage-cream","artworkMode":"full","artworkFit":"stretch","patternMode":"contour"},"print":{"paper":"A4","orientation":"portrait","paperW":210,"paperH":297,"perSheet":4,"margin":6,"gap":3,"cutLine":true}}',
                'is_system' => 1,
                'is_active' => 1,
            ],
            [
                'id' => 0,
                'template_key' => 'retail-wide',
                'template_name' => 'Retail Wide',
                'description' => 'Template awal model 2: label lebar 100 x 68 mm.',
                'design_json' => '{"schema":"roastery-label-template-v1","canvas":{"width":100,"height":68,"theme":"clean-white","artworkMode":"full","artworkFit":"cover","patternMode":"none"},"print":{"paper":"A4","orientation":"portrait","paperW":210,"paperH":297,"perSheet":4,"margin":5,"gap":2.5,"cutLine":true},"blocks":{"logo":{"x":28,"y":5,"w":44},"coffee_name":{"x":15,"y":26,"w":70,"size":20},"roastery_kicker":{"x":15,"y":19,"w":70},"taste_icons":{"x":15,"y":48,"w":70,"size":13},"info_panel":{"x":10,"y":67,"w":80,"h":27,"size":5.5}}}',
                'is_system' => 1,
                'is_active' => 1,
            ],
        ];
    }
}
