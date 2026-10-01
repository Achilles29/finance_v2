<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read-only cross-module view for operational cost decisions.  It deliberately
 * keeps purchasing, production, HPP, shrinkage, and cash movement separate:
 * those flows happen at different times and must never be summed as one cost.
 */
class Cost_control_model extends CI_Model
{
    private $tableFieldCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_report_model');
    }

    public function division_options(): array
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return [];
        }

        $name = $this->division_name_column();
        if ($name === '') {
            return [];
        }
        return $this->db->select('id, ' . $name . ' AS division_name', false)
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by($name, 'ASC')
            ->get()->result_array();
    }

    public function dashboard(array $filters): array
    {
        $salesOverview = $this->sales_overview($filters);
        $purchases = $this->material_purchase_summary($filters);
        $inventoryFlow = $this->inventory_flow_summary($filters);
        $production = $this->production_summary($filters);
        $shrinkage = $this->shrinkage_summary($filters);
        $cashExpense = $this->cash_expense_summary($filters);
        $operationalExpense = $this->operational_expense_summary($filters, $cashExpense);
        $marginAnalysis = $this->margin_analysis($filters, $shrinkage, $operationalExpense, $purchases);

        $netSales = (float)($salesOverview['net_sales'] ?? 0);
        $hpp = (float)($salesOverview['hpp_final_amount'] ?? 0);
        $grossProfit = (float)($salesOverview['gross_profit'] ?? ($netSales - $hpp));
        $shrinkageTotal = (float)($shrinkage['total'] ?? 0);
        $cashTotal = (float)($operationalExpense['total'] ?? 0);

        return [
            'summary' => [
                'net_sales' => $netSales,
                'hpp_final' => $hpp,
                'hpp_ratio_pct' => $netSales > 0 ? ($hpp / $netSales * 100) : 0,
                'gross_profit' => $grossProfit,
                'gross_margin_pct' => $netSales > 0 ? ($grossProfit / $netSales * 100) : 0,
                'material_purchase_value' => (float)($purchases['total'] ?? 0),
                'po_receipt_value' => (float)($inventoryFlow['po_receipt_value'] ?? 0),
                'po_receipt_count' => (int)($inventoryFlow['po_receipt_count'] ?? 0),
                'po_direct_division_value' => (float)($inventoryFlow['po_direct_division_value'] ?? 0),
                'sr_division_value' => (float)($inventoryFlow['sr_division_value'] ?? 0),
                'sr_fulfillment_count' => (int)($inventoryFlow['sr_fulfillment_count'] ?? 0),
                'warehouse_stock_value' => (float)($inventoryFlow['warehouse_stock_value'] ?? 0),
                'division_stock_value' => (float)($inventoryFlow['division_stock_value'] ?? 0),
                'material_usage_value' => (float)($production['material_usage_value'] ?? 0),
                'production_cost' => (float)($production['total_cost'] ?? 0),
                'production_batch_count' => (int)($production['batch_count'] ?? 0),
                'waste_total' => $shrinkageTotal,
                'waste_ratio_pct' => $netSales > 0 ? ($shrinkageTotal / $netSales * 100) : 0,
                'material_adjustment_minus' => (float)($shrinkage['material_total'] ?? 0),
                'component_adjustment_minus' => (float)($shrinkage['component_total'] ?? 0),
                'material_adjustment_ratio_pct' => (float)($production['material_usage_value'] ?? 0) > 0
                    ? ((float)($shrinkage['material_total'] ?? 0) / (float)$production['material_usage_value'] * 100) : 0,
                'component_adjustment_ratio_pct' => (float)($production['total_cost'] ?? 0) > 0
                    ? ((float)($shrinkage['component_total'] ?? 0) / (float)$production['total_cost'] * 100) : 0,
                'cash_expense' => $cashTotal,
                'cash_after_gross_profit' => $grossProfit - $cashTotal,
                'operating_margin_after_cash_pct' => $netSales > 0 ? (($grossProfit - $cashTotal) / $netSales * 100) : 0,
            ],
            'purchase_rows' => (array)($purchases['rows'] ?? []),
            'stock_rows' => (array)($inventoryFlow['stock_rows'] ?? []),
            'usage_rows' => (array)($production['usage_rows'] ?? []),
            'batch_rows' => (array)($production['batch_rows'] ?? []),
            'shrinkage_rows' => (array)($shrinkage['rows'] ?? []),
            'expense_rows' => (array)($cashExpense['rows'] ?? []),
            'operational_rows' => (array)($operationalExpense['rows'] ?? []),
            'expense_details' => (array)($cashExpense['details'] ?? []),
            'margin_analysis' => $marginAnalysis,
            'data_sources' => [
                'purchase_ready' => !empty($purchases['available']),
                'inventory_ready' => !empty($inventoryFlow['available']),
                'production_ready' => !empty($production['available']),
                'shrinkage_ready' => !empty($shrinkage['available']),
                'cash_ready' => !empty($cashExpense['available']),
            ],
        ];
    }

    /** Uses the same paid PO source and categories as Purchase Report. Stock
     * purchase types remain inventory capital; every other type is OPEX. */
    private function operational_expense_summary(array $filters, array $cashExpense): array
    {
        $rows = (array)($cashExpense['rows'] ?? []);
        $total = (float)($cashExpense['total'] ?? 0);
        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line') || !$this->db->table_exists('mst_purchase_type')) {
            return ['total' => $total, 'rows' => $rows];
        }
        $query = $this->db->select("CONCAT('Purchase - ', COALESCE(NULLIF(pt.type_name, ''), 'Operasional')) AS source_name", false)
            ->select('COALESCE(SUM(pol.line_subtotal), 0) AS expense_value, COUNT(DISTINCT po.id) AS transaction_count', false)
            ->from('pur_purchase_order po')->join('pur_purchase_order_line pol', 'pol.purchase_order_id = po.id')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->where('po.status', 'PAID')->where("UPPER(COALESCE(pt.type_code, '')) NOT LIKE '%STOK%'", null, false)
            ->where('po.request_date >=', $filters['date_from'])->where('po.request_date <=', $filters['date_to']);
        $this->apply_division_filter($query, 'po.destination_division_id', $filters);
        $this->apply_location_filter($query, 'po.destination_type', $filters);
        $purchaseRows = $query->group_by('pt.id')->group_by('pt.type_name')->get()->result_array();
        foreach ($purchaseRows as $row) {
            $total += (float)($row['expense_value'] ?? 0);
            $rows[] = $row;
        }
        usort($rows, static function (array $left, array $right): int { return (float)($right['expense_value'] ?? 0) <=> (float)($left['expense_value'] ?? 0); });
        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * This deliberately reuses the final POS sales report aggregate. It keeps
     * HPP based on each order's saved live-HPP snapshot, extras, refunds, and
     * deficit corrections instead of reading today's master HPP values.
     */
    private function margin_analysis(array $filters, array $shrinkage, array $cashExpense, array $purchases): array
    {
        $sales = $this->Pos_report_model->sales_summary_report([
            'q' => '', 'status' => 'ALL', 'order_scope' => 'ALL', 'service_type' => 'ALL',
            'payment_method_id' => 0, 'outlet_id' => 0,
            'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
            'page' => 1, 'limit' => 1,
        ]);
        $sales = (array)($sales['overview'] ?? []);
        $stock = $this->inventory_capital_delta($filters);
        $netSales = (float)($sales['net_sales'] ?? 0);
        $hpp = (float)($sales['hpp_final_amount'] ?? 0);
        $grossProfit = (float)($sales['gross_profit'] ?? ($netSales - $hpp));
        $cash = (float)($cashExpense['total'] ?? 0);
        $adjustment = (float)($shrinkage['total'] ?? 0);
        $days = max(1, (int)((strtotime((string)$filters['date_to']) - strtotime((string)$filters['date_from'])) / 86400) + 1);
        $componentStockReliable = (float)$stock['component_opening'] >= 0 && (float)$stock['component_closing'] >= 0;
        // A negative monthly component value is an integrity anomaly, not a
        // negative operating asset. Keep it visible but exclude it from the
        // consolidated capital ratio until reconciliation is completed.
        $openingCapital = (float)$stock['material_opening'] + ($componentStockReliable ? (float)$stock['component_opening'] : 0.0);
        $closingCapital = (float)$stock['material_closing'] + ($componentStockReliable ? (float)$stock['component_closing'] : 0.0);
        $capitalDelta = $closingCapital - $openingCapital;

        return [
            'net_sales' => $netSales,
            'hpp_final' => $hpp,
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $netSales > 0 ? $grossProfit / $netSales * 100 : 0,
            'cash_expense' => $cash,
            'cash_expense_pct' => $netSales > 0 ? $cash / $netSales * 100 : 0,
            'cash_coverage_pct' => $cash > 0 ? $grossProfit / $cash * 100 : 0,
            'adjustment_total' => $adjustment,
            'adjustment_pct_sales' => $netSales > 0 ? $adjustment / $netSales * 100 : 0,
            'operating_contribution' => $grossProfit - $cash,
            'operating_contribution_pct' => $netSales > 0 ? ($grossProfit - $cash) / $netSales * 100 : 0,
            'contribution_after_adjustment' => $grossProfit - $cash - $adjustment,
            'contribution_after_adjustment_pct' => $netSales > 0 ? ($grossProfit - $cash - $adjustment) / $netSales * 100 : 0,
            'material_opening' => $stock['material_opening'],
            'material_closing' => $stock['material_closing'],
            'material_delta' => $stock['material_closing'] - $stock['material_opening'],
            'component_opening' => $stock['component_opening'],
            'component_closing' => $stock['component_closing'],
            'component_delta' => $stock['component_closing'] - $stock['component_opening'],
            'component_stock_reconciliation_required' => !$componentStockReliable,
            'opening_capital' => $openingCapital,
            'closing_capital' => $closingCapital,
            'capital_delta' => $capitalDelta,
            'capital_delta_purchase_pct' => (float)($purchases['total'] ?? 0) > 0 ? $capitalDelta / (float)$purchases['total'] * 100 : 0,
            'closing_capital_sales_pct' => $netSales > 0 ? $closingCapital / $netSales * 100 : 0,
            'stock_coverage_days' => $hpp > 0 ? $closingCapital / ($hpp / $days) : 0,
            'period_days' => $days,
        ];
    }

    /** Monthly ledgers preserve the opening balance; current-month closing is
     * the live ending capital for the selected report period. */
    private function inventory_capital_delta(array $filters): array
    {
        $result = ['material_opening' => 0.0, 'material_closing' => 0.0, 'component_opening' => 0.0, 'component_closing' => 0.0];
        $openingMonth = date('Y-m-01', strtotime((string)$filters['date_from']));
        $closingMonth = date('Y-m-01', strtotime((string)$filters['date_to']));
        $materialTables = ['inv_warehouse_monthly_stock', 'inv_division_monthly_stock'];
        foreach ($materialTables as $table) {
            if (!$this->db->table_exists($table)) {
                continue;
            }
            $open = $this->db->select('COALESCE(SUM(opening_total_value), 0) AS value', false)->from($table)->where('month_key', $openingMonth);
            if ($table === 'inv_division_monthly_stock') {
                $this->apply_division_filter($open, 'division_id', $filters);
                $this->apply_location_filter($open, 'destination_type', $filters);
            }
            $result['material_opening'] += (float)($open->get()->row_array()['value'] ?? 0);

            // CI's query builder is shared. Execute the opening query before
            // constructing closing so a table is never appended twice.
            $close = $this->db->select('COALESCE(SUM(total_value), 0) AS value', false)->from($table)->where('month_key', $closingMonth);
            if ($table === 'inv_division_monthly_stock') {
                $this->apply_division_filter($close, 'division_id', $filters);
                $this->apply_location_filter($close, 'destination_type', $filters);
            }
            $result['material_closing'] += (float)($close->get()->row_array()['value'] ?? 0);
        }
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $open = $this->db->select('COALESCE(SUM(opening_total_value), 0) AS value', false)->from('inv_component_monthly_stock')->where('month_key', $openingMonth);
            $this->apply_division_filter($open, 'division_id', $filters);
            $this->apply_location_filter($open, 'location_type', $filters);
            $result['component_opening'] = (float)($open->get()->row_array()['value'] ?? 0);

            $close = $this->db->select('COALESCE(SUM(total_value), 0) AS value', false)->from('inv_component_monthly_stock')->where('month_key', $closingMonth);
            $this->apply_division_filter($close, 'division_id', $filters);
            $this->apply_location_filter($close, 'location_type', $filters);
            $result['component_closing'] = (float)($close->get()->row_array()['value'] ?? 0);
        }
        return $result;
    }

    /**
     * Separates receipt, transfer, and remaining FIFO balances. A Store
     * Request is a movement of existing stock, never a second purchase.
     */
    private function inventory_flow_summary(array $filters): array
    {
        $result = [
            'available' => false,
            'po_receipt_value' => 0.0,
            'po_receipt_count' => 0,
            'po_direct_division_value' => 0.0,
            'sr_division_value' => 0.0,
            'sr_fulfillment_count' => 0,
            'warehouse_stock_value' => 0.0,
            'division_stock_value' => 0.0,
            'stock_rows' => [],
        ];

        if ($this->db->table_exists('pur_purchase_receipt') && $this->db->table_exists('pur_purchase_receipt_line') && $this->db->table_exists('pur_purchase_order_line')) {
            $usagePurpose = $this->field_exists('pur_purchase_receipt_line', 'usage_purpose');
            $receiptQuery = $this->db->select('COALESCE(SUM(rl.qty_buy_received * pol.unit_price), 0) AS po_receipt_value, COUNT(DISTINCT r.id) AS po_receipt_count, COALESCE(SUM(CASE WHEN r.destination_division_id IS NOT NULL THEN rl.qty_buy_received * pol.unit_price ELSE 0 END), 0) AS po_direct_division_value', false)
                ->from('pur_purchase_receipt r')->join('pur_purchase_receipt_line rl', 'rl.purchase_receipt_id = r.id')->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id')
                ->where('r.status', 'POSTED')->where('rl.material_id IS NOT NULL', null, false)
                ->where('r.receipt_date >=', $filters['date_from'])->where('r.receipt_date <=', $filters['date_to']);
            if ($usagePurpose) {
                $receiptQuery->where("UPPER(COALESCE(rl.usage_purpose, 'BAHAN_BAKU')) = 'BAHAN_BAKU'", null, false);
            }
            $this->apply_division_filter($receiptQuery, 'r.destination_division_id', $filters);
            $this->apply_location_filter($receiptQuery, 'r.destination_type', $filters);
            $result = array_merge($result, (array)$receiptQuery->get()->row_array());
            $result['available'] = true;
        }

        if ($this->db->table_exists('pur_store_request') && $this->db->table_exists('pur_store_request_fulfillment') && $this->db->table_exists('pur_store_request_fulfillment_line')) {
            $usagePurpose = $this->field_exists('pur_store_request_fulfillment_line', 'usage_purpose');
            $srQuery = $this->db->select('COALESCE(SUM(fl.qty_content_posted * fl.unit_cost_snapshot), 0) AS sr_division_value, COUNT(DISTINCT f.id) AS sr_fulfillment_count', false)
                ->from('pur_store_request_fulfillment f')->join('pur_store_request sr', 'sr.id = f.store_request_id')->join('pur_store_request_fulfillment_line fl', 'fl.fulfillment_id = f.id')
                ->where('f.status', 'POSTED')->where('f.fulfillment_date >=', $filters['date_from'])->where('f.fulfillment_date <=', $filters['date_to']);
            if ($usagePurpose) {
                $srQuery->where("UPPER(COALESCE(fl.usage_purpose, 'BAHAN_BAKU')) = 'BAHAN_BAKU'", null, false);
            }
            $this->apply_division_filter($srQuery, 'sr.request_division_id', $filters);
            $this->apply_location_filter($srQuery, 'sr.destination_type', $filters);
            $result = array_merge($result, (array)$srQuery->get()->row_array());
            $result['available'] = true;
        }

        if (!$this->db->table_exists('inv_material_fifo_lot')) {
            return $result;
        }

        $stockQuery = $this->db->select("COALESCE(SUM(CASE WHEN l.location_scope = 'WAREHOUSE' THEN l.qty_balance * l.unit_cost ELSE 0 END), 0) AS warehouse_stock_value, COALESCE(SUM(CASE WHEN l.location_scope = 'DIVISION' THEN l.qty_balance * l.unit_cost ELSE 0 END), 0) AS division_stock_value", false)
            ->from('inv_material_fifo_lot l')->where('l.qty_balance >', 0.0001)
            ->where('COALESCE(l.material_id, 0) > 0', null, false);
        $this->apply_division_filter($stockQuery, 'l.division_id', $filters);
        $this->apply_stock_location_filter($stockQuery, $filters);
        $result = array_merge($result, (array)$stockQuery->get()->row_array());

        $stockRowsQuery = $this->db->select('COALESCE(m.material_name, "Bahan tanpa nama") AS material_name, l.location_scope, COALESCE(SUM(l.qty_balance * l.unit_cost), 0) AS stock_value', false)
            ->from('inv_material_fifo_lot l')->join('mst_material m', 'm.id = l.material_id', 'left')->where('l.qty_balance >', 0.0001)
            ->where('COALESCE(l.material_id, 0) > 0', null, false);
        $this->apply_division_filter($stockRowsQuery, 'l.division_id', $filters);
        $this->apply_stock_location_filter($stockRowsQuery, $filters);
        $result['stock_rows'] = $stockRowsQuery->group_by(['l.location_scope', 'l.material_id'])->order_by('stock_value', 'DESC')->limit(10)->get()->result_array();
        $result['available'] = true;
        return $result;
    }

    private function sales_overview(array $filters): array
    {
        $divisionId = (int)($filters['division_id'] ?? 0);
        if ($divisionId <= 0 || !$this->db->table_exists('mst_product') || !$this->db->field_exists('default_operational_division_id', 'mst_product')) {
            $sales = $this->Pos_report_model->sales_summary_report([
                'q' => '', 'status' => 'ALL', 'order_scope' => 'ALL', 'service_type' => 'ALL',
                'payment_method_id' => 0, 'outlet_id' => 0,
                'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
                'page' => 1, 'limit' => 1,
            ]);
            return (array)($sales['overview'] ?? []);
        }

        // POS product lines retain the net amount and COGS that remain after
        // refunds. Restricting them by the product's operational division
        // lets the material, production, and sales lenses use one division.
        return (array)($this->db->select('COALESCE(SUM(l.net_amount), 0) AS net_sales, COALESCE(SUM(l.cogs_amount), 0) AS hpp_final_amount, COUNT(DISTINCT o.id) AS order_count', false)
            ->from('pos_order_line l')
            ->join('pos_order o', 'o.id = l.order_id')
            ->join('mst_product p', 'p.id = l.product_id')
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID'])
            ->where('l.line_status !=', 'VOID')
            ->where('p.default_operational_division_id', $divisionId)
            ->where('DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)) >= ' . $this->db->escape($filters['date_from']), null, false)
            ->where('DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)) <= ' . $this->db->escape($filters['date_to']), null, false)
            ->get()->row_array() ?: []);
    }

    private function material_purchase_summary(array $filters): array
    {
        if (!$this->db->table_exists('pur_purchase_receipt') || !$this->db->table_exists('pur_purchase_receipt_line') || !$this->db->table_exists('pur_purchase_order_line')) {
            return ['available' => false, 'total' => 0, 'rows' => []];
        }

        $usagePurpose = $this->field_exists('pur_purchase_receipt_line', 'usage_purpose');
        $base = $this->db->select('COALESCE(SUM(rl.qty_buy_received * pol.unit_price), 0) AS total', false)
            ->from('pur_purchase_receipt r')
            ->join('pur_purchase_receipt_line rl', 'rl.purchase_receipt_id = r.id')
            ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id')
            ->where('r.status', 'POSTED')
            // Legacy receipts do not populate line_kind. material_id is the
            // authoritative indicator that this receipt line is raw material.
            ->where('rl.material_id IS NOT NULL', null, false)
            ->where('r.receipt_date >=', $filters['date_from'])
            ->where('r.receipt_date <=', $filters['date_to']);
        if ($usagePurpose) {
            $base->where("UPPER(COALESCE(rl.usage_purpose, 'BAHAN_BAKU')) = 'BAHAN_BAKU'", null, false);
        }
        $this->apply_division_filter($base, 'r.destination_division_id', $filters);
        $this->apply_location_filter($base, 'r.destination_type', $filters);
        $total = (float)($base->get()->row_array()['total'] ?? 0);

        $nameExpr = 'COALESCE(NULLIF(m.material_name, ""), NULLIF(pol.snapshot_material_name, ""), NULLIF(rl.line_description, ""), "Bahan tanpa nama")';
        $rowsQuery = $this->db->select($nameExpr . ' AS material_name', false)
            ->select('COALESCE(SUM(rl.qty_buy_received * pol.unit_price), 0) AS purchase_value, COUNT(DISTINCT r.id) AS receipt_count', false)
            ->from('pur_purchase_receipt r')
            ->join('pur_purchase_receipt_line rl', 'rl.purchase_receipt_id = r.id')
            ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id')
            ->join('mst_material m', 'm.id = rl.material_id', 'left')
            ->where('r.status', 'POSTED')
            ->where('rl.material_id IS NOT NULL', null, false)
            ->where('r.receipt_date >=', $filters['date_from'])
            ->where('r.receipt_date <=', $filters['date_to']);
        if ($usagePurpose) {
            $rowsQuery->where("UPPER(COALESCE(rl.usage_purpose, 'BAHAN_BAKU')) = 'BAHAN_BAKU'", null, false);
        }
        $this->apply_division_filter($rowsQuery, 'r.destination_division_id', $filters);
        $this->apply_location_filter($rowsQuery, 'r.destination_type', $filters);
        $rows = $rowsQuery->group_by($nameExpr, false)->order_by('purchase_value', 'DESC')->limit(8)->get()->result_array();

        return ['available' => true, 'total' => $total, 'rows' => $rows];
    }

    private function production_summary(array $filters): array
    {
        if (!$this->db->table_exists('inv_component_batch')) {
            return ['available' => false, 'total_cost' => 0, 'material_usage_value' => 0, 'batch_count' => 0, 'usage_rows' => [], 'batch_rows' => []];
        }

        $summaryQuery = $this->db->select('COALESCE(SUM(b.total_input_cost), 0) AS total_cost, COUNT(*) AS batch_count', false)
            ->from('inv_component_batch b')->where('b.status', 'POSTED')
            ->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
        $this->apply_division_filter($summaryQuery, 'b.division_id', $filters);
        $this->apply_location_filter($summaryQuery, 'b.location_type', $filters);
        $summary = (array)$summaryQuery->get()->row_array();

        $materialUsage = 0.0;
        $usageRows = [];
        if ($this->db->table_exists('inv_component_batch_input')) {
            $usageQuery = $this->db->select('COALESCE(SUM(i.total_cost), 0) AS total', false)
                ->from('inv_component_batch_input i')->join('inv_component_batch b', 'b.id = i.batch_id')
                ->where('b.status', 'POSTED')->where('i.source_kind', 'MATERIAL')
                ->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
            $this->apply_division_filter($usageQuery, 'b.division_id', $filters);
            $this->apply_location_filter($usageQuery, 'b.location_type', $filters);
            $materialUsage = (float)($usageQuery->get()->row_array()['total'] ?? 0);

            $usageRowsQuery = $this->db->select('COALESCE(m.material_name, "Bahan tanpa nama") AS material_name, COALESCE(SUM(i.total_cost), 0) AS usage_value, COUNT(DISTINCT b.id) AS batch_count', false)
                ->from('inv_component_batch_input i')->join('inv_component_batch b', 'b.id = i.batch_id')
                ->join('mst_material m', 'm.id = i.material_id', 'left')->where('b.status', 'POSTED')->where('i.source_kind', 'MATERIAL')
                ->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
            $this->apply_division_filter($usageRowsQuery, 'b.division_id', $filters);
            $this->apply_location_filter($usageRowsQuery, 'b.location_type', $filters);
            $usageRows = $usageRowsQuery->group_by('i.material_id')->order_by('usage_value', 'DESC')->limit(8)->get()->result_array();
        }

        $divisionName = $this->division_name_column();
        $batchRowsQuery = $this->db->select('b.batch_no, b.batch_date, b.location_type, b.output_qty, b.total_input_cost, b.unit_cost, c.component_name')
            ->select($divisionName !== '' ? 'd.' . $divisionName . ' AS division_name' : 'NULL AS division_name', false)
            ->from('inv_component_batch b')->join('mst_component c', 'c.id = b.component_id', 'left')->join('mst_operational_division d', 'd.id = b.division_id', 'left')
            ->where('b.status', 'POSTED')->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
        $this->apply_division_filter($batchRowsQuery, 'b.division_id', $filters);
        $this->apply_location_filter($batchRowsQuery, 'b.location_type', $filters);
        $batchRows = $batchRowsQuery->order_by('b.batch_date', 'DESC')->order_by('b.id', 'DESC')->limit(8)->get()->result_array();

        return ['available' => true, 'total_cost' => (float)($summary['total_cost'] ?? 0), 'material_usage_value' => $materialUsage, 'batch_count' => (int)($summary['batch_count'] ?? 0), 'usage_rows' => $usageRows, 'batch_rows' => $batchRows];
    }

    private function shrinkage_summary(array $filters): array
    {
        $result = ['available' => false, 'material_total' => 0.0, 'component_total' => 0.0, 'total' => 0.0, 'rows' => []];
        if ($this->db->table_exists('inv_stock_adjustment') && $this->db->table_exists('inv_stock_adjustment_line')) {
            $minusValue = '(l.qty_waste_content + l.qty_spoil_content + l.qty_process_loss_content + l.qty_variance_content) * l.unit_cost';
            $summaryQuery = $this->db->select('COALESCE(SUM(' . $minusValue . '), 0) AS material_total', false)
                ->from('inv_stock_adjustment a')->join('inv_stock_adjustment_line l', 'l.adjustment_id = a.id')
                ->where('a.status', 'POSTED')->where('a.adjustment_date >=', $filters['date_from'])->where('a.adjustment_date <=', $filters['date_to']);
            $this->apply_division_filter($summaryQuery, 'a.division_id', $filters);
            $this->apply_location_filter($summaryQuery, 'a.destination_type', $filters);
            $result['material_total'] = (float)($summaryQuery->get()->row_array()['material_total'] ?? 0);

            $rowsQuery = $this->db->select('"Bahan baku" AS stock_class, COALESCE(m.material_name, i.item_name, l.profile_name, "Bahan tanpa nama") AS material_name', false)
                ->select('"Penyesuaian minus" AS adjustment_type, COALESCE(SUM(' . $minusValue . '), 0) AS shrinkage_value', false)
                ->from('inv_stock_adjustment a')->join('inv_stock_adjustment_line l', 'l.adjustment_id = a.id')->join('mst_material m', 'm.id = l.material_id', 'left')->join('mst_item i', 'i.id = l.item_id', 'left')
                ->where('a.status', 'POSTED')->where('a.adjustment_date >=', $filters['date_from'])->where('a.adjustment_date <=', $filters['date_to']);
            $this->apply_division_filter($rowsQuery, 'a.division_id', $filters);
            $this->apply_location_filter($rowsQuery, 'a.destination_type', $filters);
            $result['rows'] = $rowsQuery->group_by('l.material_id')->group_by('l.item_id')->group_by('l.profile_name')->get()->result_array();
            $result['available'] = true;
        }

        if ($this->db->table_exists('inv_component_movement_log')) {
            $componentQuery = $this->db->select('COALESCE(SUM(cm.total_cost), 0) AS component_total', false)
                ->from('inv_component_movement_log cm')->where_in('cm.movement_type', ['WASTE', 'SPOIL', 'ADJUSTMENT_MINUS'])
                ->where('cm.movement_date >=', $filters['date_from'])->where('cm.movement_date <=', $filters['date_to']);
            $this->apply_division_filter($componentQuery, 'cm.division_id', $filters);
            $this->apply_location_filter($componentQuery, 'cm.location_type', $filters);
            if ($this->field_exists('inv_component_movement_log', 'reversal_of_movement_id')) {
                $componentQuery->where('cm.reversal_of_movement_id IS NULL', null, false)
                    ->where('NOT EXISTS (SELECT 1 FROM inv_component_movement_log reversal WHERE reversal.reversal_of_movement_id = cm.id)', null, false);
            }
            $result['component_total'] = (float)($componentQuery->get()->row_array()['component_total'] ?? 0);

            $componentRows = $this->db->select('"Komponen" AS stock_class, COALESCE(c.component_name, "Komponen tanpa nama") AS material_name, REPLACE(cm.movement_type, "_", " ") AS adjustment_type, COALESCE(SUM(cm.total_cost), 0) AS shrinkage_value', false)
                ->from('inv_component_movement_log cm')->join('mst_component c', 'c.id = cm.component_id', 'left')->where_in('cm.movement_type', ['WASTE', 'SPOIL', 'ADJUSTMENT_MINUS'])
                ->where('cm.movement_date >=', $filters['date_from'])->where('cm.movement_date <=', $filters['date_to']);
            $this->apply_division_filter($componentRows, 'cm.division_id', $filters);
            $this->apply_location_filter($componentRows, 'cm.location_type', $filters);
            if ($this->field_exists('inv_component_movement_log', 'reversal_of_movement_id')) {
                $componentRows->where('cm.reversal_of_movement_id IS NULL', null, false)
                    ->where('NOT EXISTS (SELECT 1 FROM inv_component_movement_log reversal WHERE reversal.reversal_of_movement_id = cm.id)', null, false);
            }
            $result['rows'] = array_merge($result['rows'], $componentRows->group_by(['cm.component_id', 'cm.movement_type'])->get()->result_array());
            $result['available'] = true;
        }

        usort($result['rows'], static function (array $left, array $right): int { return (float)$right['shrinkage_value'] <=> (float)$left['shrinkage_value']; });
        $result['rows'] = array_slice($result['rows'], 0, 10);
        $result['total'] = $result['material_total'] + $result['component_total'];
        return $result;
    }

    private function cash_expense_summary(array $filters): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return ['available' => false, 'total' => 0, 'rows' => []];
        }
        // Purchase is shown through PO receipt value. Excluding it here avoids
        // reporting the same raw material as both HPP and operating overhead.
        $where = "m.mutation_type = 'OUT' AND COALESCE(m.ref_module, '') NOT IN ('PURCHASE', 'POS', 'FINANCE_TRANSFER', 'FINANCE_PAYABLE', 'FINANCE_RECEIVABLE')";
        $totalQuery = $this->db->select('COALESCE(SUM(m.amount), 0) AS total', false)->from('fin_account_mutation_log m')
            ->where($where, null, false)->where('m.mutation_date >=', $filters['date_from'])->where('m.mutation_date <=', $filters['date_to']);
        $this->apply_effective_mutation_filter($totalQuery, 'm');
        $total = (float)($totalQuery->get()->row_array()['total'] ?? 0);

        $sourceExpr = "CASE WHEN m.ref_module = 'PAYROLL' AND m.ref_table = 'pay_meal_disbursement' THEN 'Payroll - Uang Makan' WHEN COALESCE(m.ref_module, '') = '' OR m.ref_module = 'FINANCE' THEN 'Manual / Belum Diklasifikasikan' ELSE REPLACE(m.ref_module, '_', ' ') END";
        $rowsQuery = $this->db->select($sourceExpr . ' AS source_name', false)
            ->select('COALESCE(SUM(m.amount), 0) AS expense_value, COUNT(*) AS transaction_count', false)->from('fin_account_mutation_log m')
            ->where($where, null, false)->where('m.mutation_date >=', $filters['date_from'])->where('m.mutation_date <=', $filters['date_to']);
        $this->apply_effective_mutation_filter($rowsQuery, 'm');
        $rows = $rowsQuery->group_by('source_name', false)->order_by('expense_value', 'DESC')->limit(8)->get()->result_array();
        $detailsQuery = $this->db->select($sourceExpr . ' AS source_name, m.mutation_date, m.mutation_no, m.ref_no, m.ref_table, m.notes, m.amount', false)
            ->from('fin_account_mutation_log m')->where($where, null, false)
            ->where('m.mutation_date >=', $filters['date_from'])->where('m.mutation_date <=', $filters['date_to']);
        $this->apply_effective_mutation_filter($detailsQuery, 'm');
        $details = $detailsQuery->order_by('m.mutation_date', 'DESC')->order_by('m.id', 'DESC')->limit(100)->get()->result_array();
        return ['available' => true, 'total' => $total, 'rows' => $rows, 'details' => $details];
    }

    private function apply_division_filter($builder, string $column, array $filters): void
    {
        if ((int)($filters['division_id'] ?? 0) > 0) {
            $builder->where($column, (int)$filters['division_id']);
        }
    }

    private function apply_location_filter($builder, string $column, array $filters): void
    {
        $location = strtoupper(trim((string)($filters['location_type'] ?? 'ALL')));
        if ($location === 'REGULAR') {
            $builder->where_in($column, ['GUDANG', 'BAR', 'KITCHEN', 'ROASTERY']);
        } elseif ($location === 'EVENT') {
            $builder->where_in($column, ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT']);
        }
    }

    private function apply_stock_location_filter($builder, array $filters): void
    {
        $location = strtoupper(trim((string)($filters['location_type'] ?? 'ALL')));
        if ($location === 'REGULAR') {
            $builder->group_start()
                ->where('l.location_scope', 'WAREHOUSE')
                ->or_where_in('l.destination_type', ['GUDANG', 'BAR', 'KITCHEN', 'ROASTERY'])
                ->group_end();
        } elseif ($location === 'EVENT') {
            $builder->where_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT']);
        }
    }

    private function apply_effective_mutation_filter($builder, string $alias): void
    {
        if (!$this->field_exists('fin_account_mutation_log', 'reversal_of_mutation_id')) {
            return;
        }
        $builder->where($alias . '.reversal_of_mutation_id IS NULL', null, false)
            ->where('NOT EXISTS (SELECT 1 FROM fin_account_mutation_log reversal WHERE reversal.reversal_of_mutation_id = ' . $alias . '.id)', null, false);
    }

    private function division_name_column(): string
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return '';
        }
        return $this->field_exists('mst_operational_division', 'division_name') ? 'division_name' : ($this->field_exists('mst_operational_division', 'name') ? 'name' : '');
    }

    private function field_exists(string $table, string $field): bool
    {
        $key = $table . '.' . $field;
        if (!array_key_exists($key, $this->tableFieldCache)) {
            $this->tableFieldCache[$key] = $this->db->field_exists($field, $table);
        }
        return (bool)$this->tableFieldCache[$key];
    }
}
