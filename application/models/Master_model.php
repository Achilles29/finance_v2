<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master_model extends CI_Model
{
    public function count_purchase_catalog_vendor_filtered(string $q = '', ?int $isActive = null): int
    {
        $this->db->from('mst_purchase_catalog_vendor cv');
        $this->db->join('mst_purchase_catalog c', 'c.id = cv.catalog_id', 'left');
        $this->db->join('mst_vendor v', 'v.id = cv.vendor_id', 'left');
        if ($isActive !== null) {
            $this->db->where('cv.is_active', $isActive);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.catalog_name', $q)
                ->or_like('c.brand_name', $q)
                ->or_like('c.line_description', $q)
                ->or_like('c.profile_key', $q)
                ->or_like('v.vendor_code', $q)
                ->or_like('v.vendor_name', $q)
                ->or_like('cv.notes', $q)
                ->group_end();
        }
        return (int)$this->db->count_all_results();
    }

    public function get_purchase_catalog_vendor_filtered(string $q, int $limit, int $offset, string $orderBy = 'catalog_id', string $orderDir = 'ASC', ?int $isActive = null): array
    {
        $orderCol = trim($orderBy) !== '' ? $orderBy : 'catalog_id';
        if (strpos($orderCol, '.') === false) {
            $orderCol = 'cv.' . $orderCol;
        }

        $this->db->select('cv.*');
        $this->db->from('mst_purchase_catalog_vendor cv');
        $this->db->join('mst_purchase_catalog c', 'c.id = cv.catalog_id', 'left');
        $this->db->join('mst_vendor v', 'v.id = cv.vendor_id', 'left');
        if ($isActive !== null) {
            $this->db->where('cv.is_active', $isActive);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.catalog_name', $q)
                ->or_like('c.brand_name', $q)
                ->or_like('c.line_description', $q)
                ->or_like('c.profile_key', $q)
                ->or_like('v.vendor_code', $q)
                ->or_like('v.vendor_name', $q)
                ->or_like('cv.notes', $q)
                ->group_end();
        }
        $this->db->order_by($orderCol, $orderDir);
        $this->db->limit($limit, $offset);
        return $this->db->get()->result_array();
    }

    public function count_filtered(string $table, array $searchable, string $q = '', ?int $isActive = null, array $filters = []): int
    {
        $this->db->from($table);
        if ($isActive !== null && $this->db->field_exists('is_active', $table)) {
            $this->db->where('is_active', $isActive);
        }
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '' || !$this->db->field_exists($field, $table)) {
                continue;
            }
            $this->db->where($field, $value);
        }
        if ($q !== '' && !empty($searchable)) {
            $this->db->group_start();
            foreach ($searchable as $i => $col) {
                if ($i === 0) {
                    $this->db->like($col, $q);
                } else {
                    $this->db->or_like($col, $q);
                }
            }
            $this->db->group_end();
        }
        return (int)$this->db->count_all_results();
    }

    public function get_filtered(string $table, array $searchable, string $q, int $limit, int $offset, string $orderBy = 'id', string $orderDir = 'DESC', ?int $isActive = null, array $filters = []): array
    {
        if (in_array($table, ['mst_product_division', 'mst_product_classification', 'mst_product_category', 'mst_product'], true)) {
            return $this->get_grouped_product_master_filtered($table, $searchable, $q, $limit, $offset, $isActive, $filters);
        }

        if ($table === 'org_employee') {
            return $this->get_org_employee_filtered($searchable, $q, $limit, $offset, $isActive, $filters);
        }

        $this->db->from($table);
        if ($isActive !== null && $this->db->field_exists('is_active', $table)) {
            $this->db->where('is_active', $isActive);
        }
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '' || !$this->db->field_exists($field, $table)) {
                continue;
            }
            $this->db->where($field, $value);
        }
        if ($q !== '' && !empty($searchable)) {
            $this->db->group_start();
            foreach ($searchable as $i => $col) {
                if ($i === 0) {
                    $this->db->like($col, $q);
                } else {
                    $this->db->or_like($col, $q);
                }
            }
            $this->db->group_end();
        }
        $this->db->order_by($orderBy, $orderDir);
        $this->db->limit($limit, $offset);
        return $this->db->get()->result_array();
    }

    private function get_org_employee_filtered(array $searchable, string $q, int $limit, int $offset, ?int $isActive = null, array $filters = []): array
    {
        $this->db->select('e.*');
        $this->db->from('org_employee e');
        $this->db->join('org_division d', 'd.id = e.division_id', 'left');
        $this->db->join('org_position p', 'p.id = e.position_id', 'left');

        if ($isActive !== null) {
            $this->db->where('e.is_active', $isActive);
        }

        foreach ($filters as $field => $value) {
            if ($value === null || $value === '' || !$this->db->field_exists($field, 'org_employee')) {
                continue;
            }
            $this->db->where('e.' . $field, $value);
        }

        if ($q !== '' && !empty($searchable)) {
            $this->db->group_start();
            foreach ($searchable as $i => $col) {
                $qualified = 'e.' . $col;
                if ($i === 0) {
                    $this->db->like($qualified, $q);
                } else {
                    $this->db->or_like($qualified, $q);
                }
            }
            $this->db->or_like('d.division_name', $q);
            $this->db->or_like('p.position_name', $q);
            $this->db->group_end();
        }

        $this->db->order_by('COALESCE(d.sort_order, 999999)', 'ASC', false);
        $this->db->order_by('d.division_name', 'ASC');
        $this->db->order_by('COALESCE(p.sort_order, 999999)', 'ASC', false);
        $this->db->order_by('p.position_name', 'ASC');
        $this->db->order_by('e.employee_name', 'ASC');
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    public function get_by_id(string $table, int $id): ?array
    {
        return $this->db->get_where($table, ['id' => $id])->row_array() ?: null;
    }

    private function next_sort_order(string $table): int
    {
        if (!$this->db->table_exists($table) || !$this->db->field_exists('sort_order', $table)) {
            return 1;
        }

        $row = $this->db
            ->select('COALESCE(MAX(sort_order), 0) AS max_sort_order', false)
            ->from($table)
            ->get()
            ->row_array();

        return max(1, ((int)($row['max_sort_order'] ?? 0)) + 1);
    }

    private function normalize_sort_order(string $table, array $data, ?int $id = null): array
    {
        if (!$this->db->field_exists('sort_order', $table) || !array_key_exists('sort_order', $data)) {
            return $data;
        }

        $raw = $data['sort_order'];
        if ($raw === '' || $raw === null) {
            if ($id !== null && $id > 0) {
                $existing = $this->get_by_id($table, $id);
                $existingSort = (int)($existing['sort_order'] ?? 0);
                $data['sort_order'] = $existingSort > 0 ? $existingSort : $this->next_sort_order($table);
            } else {
                $data['sort_order'] = $this->next_sort_order($table);
            }
            return $data;
        }

        $data['sort_order'] = max(1, (int)$raw);
        return $data;
    }

    public function insert(string $table, array $data): int
    {
        $data = $this->normalize_sort_order($table, $data);
        $this->db->insert($table, $data);
        return (int)$this->db->insert_id();
    }

    public function update(string $table, int $id, array $data): bool
    {
        $data = $this->normalize_sort_order($table, $data, $id);
        return $this->db->where('id', $id)->update($table, $data);
    }

    public function toggle_active(string $table, int $id): bool
    {
        $this->db->where('id', $id);
        $this->db->set('is_active', 'IF(is_active=1,0,1)', false);
        return $this->db->update($table);
    }

    public function get_options(string $table, string $valueCol = 'id', string $labelCol = 'name', bool $activeOnly = true): array
    {
        if (in_array($table, ['mst_product_classification', 'mst_product_category', 'mst_product'], true)) {
            return $this->get_grouped_product_master_options($table, $valueCol, $labelCol, $activeOnly);
        }

        $this->db->select($valueCol . ' AS value, ' . $labelCol . ' AS label', false);
        if ($activeOnly && $this->db->field_exists('is_active', $table)) {
            $this->db->where('is_active', 1);
        }
        if ($this->db->field_exists('sort_order', $table)) {
            $this->db->order_by('sort_order', 'ASC');
        }
        $this->db->order_by($labelCol, 'ASC');
        return $this->db->get($table)->result_array();
    }

    public function search_options(string $table, string $valueCol = 'id', string $labelCol = 'name', string $q = '', int $id = 0, bool $activeOnly = true, int $limit = 20): array
    {
        if (!$this->db->table_exists($table)) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $this->db->select($valueCol . ' AS value, ' . $labelCol . ' AS label', false);
        $this->db->from($table);

        if ($activeOnly && $this->db->field_exists('is_active', $table)) {
            $this->db->where('is_active', 1);
        }

        if ($id > 0) {
            $this->db->where($valueCol, $id);
            return $this->db->limit(1)->get()->result_array();
        }

        if ($q !== '') {
            $this->db->like($labelCol, $q);
        }

        if ($this->db->field_exists('sort_order', $table)) {
            $this->db->order_by('sort_order', 'ASC');
        }
        $this->db->order_by($labelCol, 'ASC');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }

    public function exists_by_code(string $table, string $codeColumn, string $codeValue, int $excludeId = 0): bool
    {
        $this->db->where($codeColumn, $codeValue);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        return $this->db->count_all_results($table) > 0;
    }

    public function get_variable_cost_default_percent(string $scopeCode, float $fallback = 20.0): float
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return $fallback;
        }

        $row = $this->db
            ->select('default_percent')
            ->from('mst_variable_cost_default')
            ->where('scope_code', strtoupper($scopeCode))
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row || !isset($row['default_percent'])) {
            return $fallback;
        }

        return (float)$row['default_percent'];
    }

    public function generate_unique_code(string $table, string $codeColumn, string $name, int $excludeId = 0, int $maxLength = 50): string
    {
        $maxLength = max(3, $maxLength);
        $stem = $this->build_initial_code($name);
        $candidate = substr($stem, 0, $maxLength);

        if (!$this->exists_by_code($table, $codeColumn, $candidate, $excludeId)) {
            return $candidate;
        }

        $seq = 1;
        while ($seq <= 9999) {
            $suffix = str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
            $baseLen = max(1, $maxLength - strlen($suffix));
            $candidate = substr($stem, 0, $baseLen) . $suffix;
            if (!$this->exists_by_code($table, $codeColumn, $candidate, $excludeId)) {
                return $candidate;
            }
            $seq++;
        }

        return substr('AUTO' . date('His'), 0, $maxLength);
    }

    private function build_initial_code(string $name): string
    {
        $clean = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', ' ', $name)));
        if ($clean === '') {
            return 'CODE';
        }

        $parts = preg_split('/\s+/', $clean);
        $initials = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= substr($part, 0, 1);
        }

        if (strlen($initials) < 3) {
            $packed = str_replace(' ', '', $clean);
            $initials = substr($packed, 0, 3);
        }

        return $initials !== '' ? $initials : 'CODE';
    }

    private function get_grouped_product_master_filtered(string $table, array $searchable, string $q, int $limit, int $offset, ?int $isActive = null, array $filters = []): array
    {
        $alias = 't';

        $select = [$alias . '.*'];
        if ($table === 'mst_product_division') {
            $select[] = '(SELECT COUNT(1) FROM mst_product_classification pc WHERE pc.product_division_id = t.id) AS total_classification';
            $select[] = '(SELECT COUNT(1) FROM mst_product_category cat WHERE cat.product_division_id = t.id) AS total_category';
            $select[] = '(SELECT COUNT(1) FROM mst_product p WHERE p.product_division_id = t.id) AS total_product';
        } elseif ($table === 'mst_product_classification') {
            $select[] = '(SELECT COUNT(1) FROM mst_product_category cat WHERE cat.classification_id = t.id) AS total_category';
            $select[] = '(SELECT COUNT(1) FROM mst_product p WHERE p.classification_id = t.id) AS total_product';
        } elseif ($table === 'mst_product_category') {
            $select[] = '(SELECT COUNT(1) FROM mst_product p WHERE p.product_category_id = t.id) AS total_product';
        }

        $this->db->select(implode(', ', $select), false);
        $this->db->from($table . ' ' . $alias);

        if ($table === 'mst_product_classification') {
            $this->db->join('mst_product_division pd', 'pd.id = t.product_division_id', 'left');
        } elseif ($table === 'mst_product_category') {
            $this->db->join('mst_product_division pd', 'pd.id = t.product_division_id', 'left');
            $this->db->join('mst_product_classification pc', 'pc.id = t.classification_id', 'left');
        } elseif ($table === 'mst_product') {
            $this->db->join('mst_product_division pd', 'pd.id = t.product_division_id', 'left');
            $this->db->join('mst_product_classification pc', 'pc.id = t.classification_id', 'left');
            $this->db->join('mst_product_category cat', 'cat.id = t.product_category_id', 'left');
        }

        if ($isActive !== null && $this->db->field_exists('is_active', $table)) {
            $this->db->where($alias . '.is_active', $isActive);
        }
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '' || !$this->db->field_exists($field, $table)) {
                continue;
            }
            $this->db->where($alias . '.' . $field, $value);
        }
        if ($q !== '' && !empty($searchable)) {
            $this->db->group_start();
            foreach ($searchable as $i => $col) {
                $field = $alias . '.' . $col;
                if ($i === 0) {
                    $this->db->like($field, $q);
                } else {
                    $this->db->or_like($field, $q);
                }
            }
            $this->db->group_end();
        }

        $preferOwnSort = in_array($table, ['mst_product_classification', 'mst_product_category'], true);
        $this->apply_grouped_product_master_order($table, $alias, $preferOwnSort);
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    private function get_grouped_product_master_options(string $table, string $valueCol, string $labelCol, bool $activeOnly): array
    {
        $alias = 't';

        $this->db->select($alias . '.' . $valueCol . ' AS value, ' . $alias . '.' . $labelCol . ' AS label', false);
        $this->db->from($table . ' ' . $alias);

        if ($table === 'mst_product_classification') {
            $this->db->join('mst_product_division pd', 'pd.id = t.product_division_id', 'left');
        } elseif ($table === 'mst_product_category') {
            $this->db->join('mst_product_division pd', 'pd.id = t.product_division_id', 'left');
            $this->db->join('mst_product_classification pc', 'pc.id = t.classification_id', 'left');
        } elseif ($table === 'mst_product') {
            $this->db->join('mst_product_division pd', 'pd.id = t.product_division_id', 'left');
            $this->db->join('mst_product_classification pc', 'pc.id = t.classification_id', 'left');
            $this->db->join('mst_product_category cat', 'cat.id = t.product_category_id', 'left');
        }

        if ($activeOnly && $this->db->field_exists('is_active', $table)) {
            $this->db->where($alias . '.is_active', 1);
        }

        $this->apply_grouped_product_master_order($table, $alias);
        $this->db->order_by($alias . '.' . $labelCol, 'ASC');

        return $this->db->get()->result_array();
    }

    private function apply_grouped_product_master_order(string $table, string $alias = 't', bool $preferOwnSort = false): void
    {
        if ($table === 'mst_product_division') {
            $this->db->order_by($alias . '.sort_order', 'ASC');
            $this->db->order_by($alias . '.id', 'ASC');
            return;
        }

        if ($table === 'mst_product_classification') {
            if ($preferOwnSort) {
                $this->db->order_by($alias . '.sort_order', 'ASC');
                $this->db->order_by($alias . '.id', 'ASC');
                $this->db->order_by('pd.sort_order', 'ASC');
                $this->db->order_by('pd.id', 'ASC');
                return;
            }
            $this->db->order_by('pd.sort_order', 'ASC');
            $this->db->order_by('pd.id', 'ASC');
            $this->db->order_by($alias . '.sort_order', 'ASC');
            $this->db->order_by($alias . '.id', 'ASC');
            return;
        }

        if ($table === 'mst_product_category') {
            if ($preferOwnSort) {
                $this->db->order_by($alias . '.sort_order', 'ASC');
                $this->db->order_by($alias . '.id', 'ASC');
                $this->db->order_by('pd.sort_order', 'ASC');
                $this->db->order_by('pd.id', 'ASC');
                $this->db->order_by('pc.sort_order', 'ASC');
                $this->db->order_by('pc.id', 'ASC');
                return;
            }
            $this->db->order_by('pd.sort_order', 'ASC');
            $this->db->order_by('pd.id', 'ASC');
            $this->db->order_by('pc.sort_order', 'ASC');
            $this->db->order_by('pc.id', 'ASC');
            $this->db->order_by($alias . '.sort_order', 'ASC');
            $this->db->order_by($alias . '.id', 'ASC');
            return;
        }

        if ($table === 'mst_product') {
            $this->db->order_by('pd.sort_order', 'ASC');
            $this->db->order_by('pd.id', 'ASC');
            $this->db->order_by('pc.sort_order', 'ASC');
            $this->db->order_by('pc.id', 'ASC');
            $this->db->order_by('cat.sort_order', 'ASC');
            $this->db->order_by('cat.id', 'ASC');
            $this->db->order_by($alias . '.product_name', 'ASC');
            $this->db->order_by($alias . '.id', 'ASC');
        }
    }
}
