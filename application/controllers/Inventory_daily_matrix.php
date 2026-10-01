<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_daily_matrix extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Purchase_model');
    }

    public function index(): void
    {
        $canWarehouse = $this->can('purchase.stock.warehouse.matrix.index', 'view')
            || $this->can('purchase.stock.warehouse.index', 'view');
        $canMaterial = $this->can('purchase.stock.material.matrix.index', 'view')
            || $this->can('purchase.stock.division.index', 'view');
        $canComponent = $this->can('production.component.daily.index', 'view');
        if (!$canWarehouse && !$canMaterial && !$canComponent) {
            $this->require_permission('purchase.stock.material.matrix.index', 'view');
            return;
        }

        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisions = array_values(array_filter($divisions, static function (array $division) use ($scopeDivisionId): bool {
                return (int)($division['id'] ?? 0) === $scopeDivisionId;
            }));
        }
        $divisions = array_values(array_filter($divisions, static function (array $division): bool {
            $code = strtoupper(trim((string)($division['code'] ?? '')));
            $name = strtoupper(trim((string)($division['name'] ?? '')));
            return strpos($code, 'MANAGEMENT') === false
                && strpos($name, 'MANAGEMENT') === false
                && strpos($code, 'MANAJEMEN') === false
                && strpos($name, 'MANAJEMEN') === false;
        }));

        $this->render('inventory/daily_matrix_fullscreen', [
            'title' => 'Daily Inventory Matrix',
            'active_menu' => 'purchase.stock.material.matrix',
            'immersive_layout' => true,
            'divisions' => $divisions,
            'can_warehouse' => $canWarehouse,
            'can_material' => $canMaterial,
            'can_component' => $canComponent,
            'initial_month' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', (string)$this->input->get('month', true))
                ? (string)$this->input->get('month', true)
                : date('Y-m'),
            'back_url' => site_url($canMaterial ? 'inventory-material-daily' : ($canWarehouse ? 'inventory-warehouse-daily' : 'dashboard')),
            'home_url' => site_url('dashboard'),
            'warehouse_url' => site_url('inventory-warehouse-daily'),
            'material_url' => site_url('inventory-material-daily'),
            'component_url' => site_url('production/component-daily'),
        ]);
    }
}
