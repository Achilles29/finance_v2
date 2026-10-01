<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Landing_page_model extends CI_Model
{
    // ── CONFIG ────────────────────────────────────────────────────────

    public function get_config(): array
    {
        $row = $this->db->where('id', 1)->get('lp_config')->row_array();
        if (!$row) return [];

        foreach (['hero_badges', 'about_points'] as $f) {
            $decoded = !empty($row[$f]) ? json_decode($row[$f], true) : null;
            $row[$f] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    public function upsert_config(array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $exists = $this->db->where('id', 1)->count_all_results('lp_config') > 0;
        if ($exists) {
            $this->db->where('id', 1)->update('lp_config', $data);
        } else {
            $data['id'] = 1;
            $this->db->insert('lp_config', $data);
        }
    }

    // ── MENU (single source: mst_product) ───────────────────────────

    public function get_landing_products(): array
    {
        $salesSql = "(
            SELECT pol.product_id, SUM(pol.qty) AS sales_qty
            FROM pos_order_line pol
            INNER JOIN pos_order po ON po.id = pol.order_id
            WHERE po.ordered_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
              AND po.status NOT IN ('DRAFT', 'VOID', 'REFUND_FULL')
              AND pol.line_status NOT IN ('VOID', 'REFUNDED_FULL')
              AND pol.line_type <> 'BUNDLE_HEADER'
            GROUP BY pol.product_id
        ) landing_sales";

        return $this->db
            ->select(
                'p.id, p.product_code, p.product_name AS title, p.description, '
                . 'p.photo_path AS image, p.selling_price AS price, p.show_landing AS is_active, '
                . 'p.product_category_id, c.name AS category_name, '
                . 'COALESCE(landing_sales.sales_qty, 0) AS sales_qty',
                false
            )
            ->from('mst_product p')
            ->join('mst_product_category c', 'c.id = p.product_category_id', 'left')
            ->join($salesSql, 'landing_sales.product_id = p.id', 'left', false)
            ->where('p.show_landing', 1)
            ->where('p.is_active', 1)
            ->order_by('sales_qty', 'DESC')
            ->order_by('c.sort_order', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_available_landing_products(): array
    {
        return $this->db
            ->select('p.id, p.product_code, p.product_name, p.selling_price, c.name AS category_name')
            ->from('mst_product p')
            ->join('mst_product_category c', 'c.id = p.product_category_id', 'left')
            ->where('p.is_active', 1)
            ->where('p.show_landing', 0)
            ->order_by('c.sort_order', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_product_categories(): array
    {
        return $this->db
            ->select(
                'c.id, c.name, COUNT(p.id) AS product_count, '
                . 'COALESCE(SUM(CASE WHEN p.show_landing = 1 AND p.is_active = 1 THEN 1 ELSE 0 END), 0) AS landing_count',
                false
            )
            ->from('mst_product_category c')
            ->join('mst_product p', 'p.product_category_id = c.id AND p.is_active = 1', 'left')
            ->where('c.is_active', 1)
            ->group_by(['c.id', 'c.name', 'c.sort_order'])
            ->having('COUNT(p.id) >', 0, false)
            ->order_by('c.sort_order', 'ASC')
            ->order_by('c.name', 'ASC')
            ->get()
            ->result_array();
    }

    public function find_product(int $id): ?array
    {
        $row = $this->db->where('id', $id)->get('mst_product')->row_array();
        return $row ?: null;
    }

    public function add_product_to_landing(int $id): bool
    {
        $this->db->where('id', $id)->where('is_active', 1)->update('mst_product', ['show_landing' => 1]);
        return $this->db->affected_rows() > 0;
    }

    public function remove_product_from_landing(int $id): bool
    {
        $this->db->where('id', $id)->update('mst_product', ['show_landing' => 0]);
        return $this->db->affected_rows() > 0;
    }

    public function toggle_landing_product(int $id): int
    {
        $row = $this->db->select('show_landing')->where('id', $id)->get('mst_product')->row_array();
        if (!$row) return -1;

        $new = (int)$row['show_landing'] === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('mst_product', ['show_landing' => $new]);
        return $new;
    }

    // ── GALLERY ───────────────────────────────────────────────────────

    public function get_gallery(bool $all = false): array
    {
        $q = $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC');
        if (!$all) $q->where('is_active', 1);
        return $q->get('lp_gallery')->result_array();
    }

    public function find_gallery(int $id): ?array
    {
        $row = $this->db->where('id', $id)->get('lp_gallery')->row_array();
        return $row ?: null;
    }

    public function insert_gallery(array $data): int
    {
        $this->db->insert('lp_gallery', $data);
        return (int)$this->db->insert_id();
    }

    public function update_gallery(int $id, array $data): void
    {
        $this->db->where('id', $id)->update('lp_gallery', $data);
    }

    public function delete_gallery(int $id): void
    {
        $this->db->where('id', $id)->delete('lp_gallery');
    }

    public function toggle_gallery(int $id): int
    {
        $row = $this->db->select('is_active')->where('id', $id)->get('lp_gallery')->row_array();
        if (!$row) return 0;
        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('lp_gallery', ['is_active' => $new]);
        return $new;
    }

    public function reorder_gallery(array $ids): void
    {
        foreach ($ids as $order => $id) {
            $this->db->where('id', (int)$id)->update('lp_gallery', ['sort_order' => (int)$order]);
        }
    }

    public function next_gallery_sort(): int
    {
        $row = $this->db->select_max('sort_order')->get('lp_gallery')->row_array();
        return (int)($row['sort_order'] ?? -1) + 1;
    }

    // ── EMBED ─────────────────────────────────────────────────────────

    public function get_embed(bool $all = false): array
    {
        $q = $this->db->order_by('embed_type', 'ASC')->order_by('sort_order', 'ASC')->order_by('id', 'ASC');
        if (!$all) $q->where('is_active', 1);
        return $q->get('lp_embed')->result_array();
    }

    public function find_embed(int $id): ?array
    {
        $row = $this->db->where('id', $id)->get('lp_embed')->row_array();
        return $row ?: null;
    }

    public function insert_embed(array $data): int
    {
        $this->db->insert('lp_embed', $data);
        return (int)$this->db->insert_id();
    }

    public function update_embed(int $id, array $data): void
    {
        $this->db->where('id', $id)->update('lp_embed', $data);
    }

    public function delete_embed(int $id): void
    {
        $this->db->where('id', $id)->delete('lp_embed');
    }

    public function toggle_embed(int $id): int
    {
        $row = $this->db->select('is_active')->where('id', $id)->get('lp_embed')->row_array();
        if (!$row) return 0;
        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('lp_embed', ['is_active' => $new]);
        return $new;
    }

    public function next_embed_sort(string $type): int
    {
        $row = $this->db->select_max('sort_order')->where('embed_type', $type)->get('lp_embed')->row_array();
        return (int)($row['sort_order'] ?? -1) + 1;
    }

    // ── LINKS ─────────────────────────────────────────────────────────

    public function get_links(bool $all = false): array
    {
        $q = $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC');
        if (!$all) $q->where('is_active', 1);
        return $q->get('lp_links')->result_array();
    }

    public function find_link(int $id): ?array
    {
        $row = $this->db->where('id', $id)->get('lp_links')->row_array();
        return $row ?: null;
    }

    public function insert_link(array $data): int
    {
        $this->db->insert('lp_links', $data);
        return (int)$this->db->insert_id();
    }

    public function update_link(int $id, array $data): void
    {
        $this->db->where('id', $id)->update('lp_links', $data);
    }

    public function delete_link(int $id): void
    {
        $this->db->where('id', $id)->delete('lp_links');
    }

    public function toggle_link(int $id): int
    {
        $row = $this->db->select('is_active')->where('id', $id)->get('lp_links')->row_array();
        if (!$row) return 0;
        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('lp_links', ['is_active' => $new]);
        return $new;
    }

    public function reorder_links(array $ids): void
    {
        foreach ($ids as $order => $id) {
            $this->db->where('id', (int)$id)->update('lp_links', ['sort_order' => (int)$order]);
        }
    }

    public function next_link_sort(): int
    {
        $row = $this->db->select_max('sort_order')->get('lp_links')->row_array();
        return (int)($row['sort_order'] ?? -1) + 1;
    }
}
