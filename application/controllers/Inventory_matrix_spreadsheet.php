<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Inventory_matrix_spreadsheet_config.php';
require_once APPPATH . 'libraries/Inventory_matrix_spreadsheet_export.php';
require_once APPPATH . 'libraries/Inventory_matrix_spreadsheet_sync.php';
require_once APPPATH . 'libraries/Product_sheet_client.php';

class Inventory_matrix_spreadsheet extends MY_Controller
{
    private const CSRF_KEY = 'inventory_matrix_spreadsheet_csrf';
    private const PAGES = ['purchase.stock.warehouse.matrix.index', 'purchase.stock.material.matrix.index', 'production.component.daily.index'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model(['Purchase_model', 'Production_model']);
    }

    private function authorize(bool $global = false): void
    {
        foreach (self::PAGES as $page) $this->require_permission($page, 'view');
        if ($global && $this->active_division_id() !== null) show_error('Sinkronisasi spreadsheet lintas divisi hanya tersedia untuk pengguna dengan cakupan seluruh divisi.', 403, 'Forbidden');
    }

    private function token(): string
    {
        $token = (string)$this->session->userdata(self::CSRF_KEY);
        if (!preg_match('/\A[0-9a-f]{64}\z/D', $token)) { $token = bin2hex(random_bytes(32)); $this->session->set_userdata(self::CSRF_KEY, $token); }
        return $token;
    }

    private function requirePostToken(): bool
    {
        $provided = (string)$this->input->post('inventory_matrix_csrf', false);
        $expected = (string)$this->session->userdata(self::CSRF_KEY);
        if ($this->input->method(true) !== 'POST' || !preg_match('/\A[0-9a-f]{64}\z/D', $provided) || !hash_equals($expected, $provided)) {
            $this->json_error('Permintaan tidak valid atau sesi kedaluwarsa.', 403); return false;
        }
        return true;
    }

    public function settings(): void
    {
        $this->authorize(true);
        $settings = Inventory_matrix_spreadsheet_config::load();
        $this->render('inventory/matrix_spreadsheet_settings', [
            'title' => 'Pengaturan Daily Matrix Spreadsheet',
            'settings' => $settings,
            'divisions' => $this->Purchase_model->list_active_operational_divisions(),
            'csrf' => $this->token(),
            'discover_url' => site_url('inventory/daily-matrix/spreadsheet/discover'),
            'save_url' => site_url('inventory/daily-matrix/spreadsheet/save'),
            'back_url' => site_url('inventory-material-daily'),
        ]);
    }

    public function discover(): void
    {
        $this->authorize(true);
        if (!$this->requirePostToken()) return;
        try {
            $spreadsheetId = Inventory_matrix_spreadsheet_config::parseSpreadsheetUrl((string)$this->input->post('spreadsheet_url', true));
            $data = (new Inventory_matrix_spreadsheet_sync())->discover($spreadsheetId);
            $this->json_ok($data);
        } catch (Throwable $e) { $this->json_error($e->getMessage(), 422); }
    }

    public function save(): void
    {
        $this->authorize(true);
        if (!$this->requirePostToken()) return;
        try {
            $spreadsheetId = Inventory_matrix_spreadsheet_config::parseSpreadsheetUrl((string)$this->input->post('spreadsheet_url', true));
            $divisions = $this->Purchase_model->list_active_operational_divisions();
            $map = [];
            foreach ($divisions as $division) {
                $id = (int)($division['id'] ?? 0);
                $value = $this->input->post('division_sheet_' . $id, true);
                if ($id > 0 && $value !== null && $value !== '') $map[(string)$id] = $value;
            }
            $data = Inventory_matrix_spreadsheet_config::normalize([
                'spreadsheet_id' => $spreadsheetId,
                'warehouse_sheet_id' => $this->input->post('warehouse_sheet_id', true),
                'division_sheets' => $map,
            ]);
            $discovered = (new Inventory_matrix_spreadsheet_sync())->discover($spreadsheetId);
            $validIds = array_column($discovered['tabs'], 'id');
            $selected = array_merge([$data['warehouse_sheet_id']], array_values($data['division_sheets']));
            if (count(array_unique($selected)) !== count($selected)) throw new RuntimeException('Setiap sumber stok harus memakai tab yang berbeda.');
            foreach ($selected as $sheetId) if (!in_array($sheetId, $validIds, true)) throw new RuntimeException('Pemetaan memuat tab yang tidak ada pada spreadsheet. Muat daftar tab kembali.');
            Inventory_matrix_spreadsheet_config::save($data);
            $this->json_ok(['message' => 'Pengaturan tersimpan aman di server.', 'spreadsheet_title' => $discovered['title']]);
        } catch (Throwable $e) { $this->json_error($e->getMessage(), 422); }
    }

    public function sync(): void
    {
        $this->authorize(true);
        if (!$this->requirePostToken()) return;
        $month = trim((string)$this->input->post('month', true));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) { $this->json_error('Pilih bulan yang valid.', 422); return; }
        try {
            $settings = Inventory_matrix_spreadsheet_config::load();
            if (!$settings) throw new RuntimeException('Spreadsheet belum dikonfigurasi. Buka Pengaturan & Panduan terlebih dahulu.');
            $payloads = $this->collectSnapshots($month, $settings);
            $result = (new Inventory_matrix_spreadsheet_sync())->sync($settings, $month, $payloads);
            $this->json_ok($result);
        } catch (Throwable $e) { log_message('error', 'Inventory matrix spreadsheet sync failed: ' . $e->getMessage()); $this->json_error($e->getMessage(), 422); }
    }

    private function collectSnapshots(string $month, array $settings): array
    {
        $start = $month . '-01'; $end = date('Y-m-t', strtotime($start));
        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $names = []; $divisionRows = [];
        foreach ($divisions as $division) {
            $id = (int)($division['id'] ?? 0); if ($id <= 0) continue;
            $names[(string)$id] = (string)($division['name'] ?? $division['division_name'] ?? $division['code'] ?? ('Divisi ' . $id));
        }
        foreach ($settings['division_sheets'] as $id => $_sheet) {
            if (!isset($names[(string)$id])) throw new RuntimeException('Pemetaan spreadsheet memuat divisi yang sudah tidak aktif. Perbarui pengaturan.');
        }
        foreach ($divisions as $division) {
            $id = (int)($division['id'] ?? 0); if ($id <= 0) continue;
            $raw = []; $limit = 500; $offset = 0; $total = null;
            do {
                $page = $this->Purchase_model->list_material_daily_matrix($month, '', (int)$id, $start, $end, $limit, 'ALL', $offset);
                $rows = (array)($page['rows'] ?? []); $raw = array_merge($raw, $rows);
                if (isset($page['total_count'])) $total = (int)$page['total_count'];
                if ($total === null && count($rows) >= $limit) throw new RuntimeException('Sumber matriks bahan baku tidak memberikan total baris dan mencapai batas pagination. Ekspor dihentikan agar tidak terpotong.');
                $offset += count($rows);
            } while ($total !== null && $offset < $total && count($rows) > 0);
            if ($total !== null && count($raw) < $total) throw new RuntimeException('Matriks bahan baku divisi ' . $names[(string)$id] . ' terpotong; ekspor dihentikan agar tidak menghasilkan snapshot parsial.');
            $raw = $this->attachCategoryNames($raw, 'material_id', 'mst_material', 'item_category_id', 'mst_item_category');
            $projected = Inventory_matrix_spreadsheet_export::materialRows($raw, $names[(string)$id]);
            $componentRows = [];
            foreach ([$this->divisionCode($id), $this->divisionCode($id) . '_EVENT'] as $location) {
                $matrix = $this->Production_model->component_daily_matrix(['month' => $month, 'location_type' => $location, 'division_id' => $id, 'type' => ''], 0);
                $componentRows = array_merge($componentRows, (array)($matrix['rows'] ?? []));
            }
            $componentRows = $this->attachCategoryNames($componentRows, 'component_id', 'mst_component', 'component_category_id', 'mst_component_category');
            $allRows = array_merge($projected, Inventory_matrix_spreadsheet_export::componentRows($componentRows, $names[(string)$id]));
            if (!isset($settings['division_sheets'][(string)$id])) {
                if ($allRows) throw new RuntimeException('Divisi ' . $names[(string)$id] . ' memiliki stok tetapi belum dipetakan ke tab spreadsheet. Atur tabnya terlebih dahulu.');
                continue;
            }
            $divisionRows[(string)$id] = $allRows;
        }
        foreach ($names as $id => $_name) if (!array_key_exists((string)$id, $settings['division_sheets'])) {
            throw new RuntimeException('Pemetaan tab belum mencakup semua divisi aktif. Buka pengaturan lalu pilih tab tiap divisi.');
        }
        $warehouse = $this->Purchase_model->list_warehouse_daily_matrix($month, '', $start, $end, 5000);
        if (count((array)($warehouse['rows'] ?? [])) >= 5000) throw new RuntimeException('Matriks gudang mencapai batas aman 5.000 baris. Persempit sumber data sebelum ekspor.');
        $warehouseRows = $this->attachCategoryNames((array)($warehouse['rows'] ?? []), 'material_id', 'mst_material', 'item_category_id', 'mst_item_category');
        return ['warehouse' => Inventory_matrix_spreadsheet_export::warehouseRows($warehouseRows), 'divisions' => $divisionRows, 'division_names' => $names];
    }

    private function attachCategoryNames(array $rows, string $idField, string $entityTable, string $categoryIdField, string $categoryTable): array
    {
        if (!$rows) return $rows;
        if (!$this->db->table_exists($entityTable) || !$this->db->field_exists($categoryIdField, $entityTable)
            || !$this->db->table_exists($categoryTable) || !$this->db->field_exists('name', $categoryTable)) {
            throw new RuntimeException('Master kategori untuk ' . $entityTable . ' tidak tersedia; ekspor dihentikan agar kategori tidak kosong.');
        }
        $ids = [];
        foreach ($rows as $row) if ((int)($row[$idField] ?? 0) > 0) $ids[(int)$row[$idField]] = (int)$row[$idField];
        $names = [];
        if ($ids) {
            $matches = $this->db->select('e.id AS entity_id, c.name AS category_name', false)
                ->from($entityTable . ' e')->join($categoryTable . ' c', 'c.id = e.' . $categoryIdField, 'left')
                ->where_in('e.id', array_values($ids))->get()->result_array();
            foreach ($matches as $match) $names[(int)$match['entity_id']] = (string)($match['category_name'] ?? '');
        }
        foreach ($rows as &$row) $row['category_name'] = $names[(int)($row[$idField] ?? 0)] ?? '';
        unset($row);
        return $rows;
    }

    private function divisionCode(int $divisionId): string
    {
        foreach ($this->Purchase_model->list_active_operational_divisions() as $row) {
            if ((int)($row['id'] ?? 0) === $divisionId) return strtoupper(trim((string)($row['code'] ?? $row['division_code'] ?? '')));
        }
        throw new RuntimeException('Kode divisi tidak ditemukan.');
    }

    private function json_ok(array $data): void { $this->output->set_content_type('application/json')->set_output(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)); }
    private function json_error(string $message, int $status): void { $this->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)); }
}
