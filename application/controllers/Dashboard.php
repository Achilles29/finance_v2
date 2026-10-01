<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    private const PAGE_INDEX = 'dashboard.index';

    /** @var array<string,bool> */
    private $dashboardTableReadyCache = [];

    /** @var array<string,bool> */
    private $registeredPageCache = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->require_registered_page_permission(self::PAGE_INDEX);

        require_once APPPATH.'libraries/Feature_policy.php';
        $features = Feature_policy::runtime();
        // Do not query the legacy dashboard's mixed paid datasets for a basic home page.
        if ($features->managed() && !$features->all(['SALES_REPORTING', 'INVENTORY_WAREHOUSE', 'INVENTORY_RECON', 'COMPONENT_PRODUCTION', 'HPP_CONTROL', 'FINANCE_ADVANCED', 'PERIOD_AUDIT'])) {
            $this->render('system/feature_home', ['title'=>'Dashboard', 'active_menu'=>'dashboard', 'features'=>$features]);
            return;
        }

        $filters = $this->dashboard_filters();
        $stockCards = $this->dashboard_stock_cards();
        $salesOverview = $this->dashboard_pos_sales_overview($filters);
        $chartFilters = $this->dashboard_chart_filters();
        $activeDeficitRows = $this->dashboard_active_deficit_rows();
        $data = [
            'title'       => 'Dashboard',
            'active_menu' => 'dashboard',
            'filters' => $filters,
            'kpi' => $this->dashboard_kpi($filters, $stockCards, $salesOverview),
            'trend' => $this->dashboard_trend_series($chartFilters),
            'chart_filters' => $chartFilters,
            'pos_status_rows' => $this->dashboard_pos_status_rows($filters),
            'pos_scope_rows' => $this->dashboard_pos_scope_rows($filters),
            'stock_breakdown' => $this->dashboard_stock_breakdown(),
            'component_production_suggestions' => $this->dashboard_component_production_suggestions(8),
            'stock_product_live' => $this->dashboard_stock_product_live(),
            'stagnant_lot_analysis' => $this->dashboard_stagnant_lot_analysis(),
            'reconcile_mismatch' => $this->dashboard_reconcile_mismatch_summary(),
            'adjustment_summary' => $this->dashboard_adjustment_summary(),
            'top_selling_products' => $this->dashboard_top_selling_products(),
            'prod_live_hidden_cats' => $this->dashboard_load_prod_live_hidden_cats(),
            'critical_stock_rows' => $this->dashboard_critical_stock_rows(0),
            'active_deficit_rows' => $activeDeficitRows,
            'recent_activity' => $this->dashboard_recent_activity($filters),
        ];

        $this->render('dashboard/index', $data);
    }

    /**
     * Halaman baca-saja untuk melihat component BASE/PREPARE yang perlu
     * diproses karena stoknya kosong/kritis sementara bahan resepnya tersedia.
     */
    public function production_suggestions(): void
    {
        // Halaman ini satu rumpun dengan Dashboard, sehingga mengikuti akses Dashboard.
        $this->require_registered_page_permission(self::PAGE_INDEX);

        $divisionId = (int)$this->input->get('division_id', true);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }

        $analysis = $this->dashboard_component_production_suggestions(250, $divisionId > 0 ? $divisionId : null);

        $this->render('dashboard/component_production_suggestions', [
            'title' => 'Analisa & Saran Produksi',
            'active_menu' => 'dashboard',
            'analysis' => $analysis,
            'filter_division_id' => $divisionId,
        ]);
    }

    public function product_recipe_stock()
    {
        $productId = (int)$this->input->get('product_id', true);
        if ($productId <= 0) {
            $this->json_error('product_id wajib diisi.', 422);
            return;
        }

        if (!$this->dashboard_table_ready('mst_product_recipe')) {
            $this->json_ok(['recipe' => []]);
            return;
        }

        $hasMaterial  = $this->dashboard_table_ready('inv_division_monthly_stock');
        $hasComponent = $this->dashboard_table_ready('inv_component_monthly_stock');
        $targetMonth  = date('Y-m-01');

        // Ambil default_operational_division_id produk sebagai fallback
        $productRow = $this->db->query(
            "SELECT default_operational_division_id FROM mst_product WHERE id = " . (int)$productId
        )->row_array();
        $defaultDivId = (int)($productRow['default_operational_division_id'] ?? 0);

        $sql = "
            SELECT
                r.id,
                r.line_type,
                r.ingredient_role,
                r.qty,
                COALESCE(r.uom_id, 0) AS recipe_uom_id,
                COALESCE(r.source_division_id, 0) AS source_division_id,
                COALESCE(u.code, '') AS uom_code,
                COALESCE(m.material_name, c.component_name, 'Unknown') AS ingredient_name,
                COALESCE(m.material_code, '') AS material_code,
                r.material_item_id,
                r.component_id,
                mi.material_id,
                COALESCE(c.component_type, '') AS component_type
            FROM mst_product_recipe r
            LEFT JOIN mst_uom u ON u.id = r.uom_id
            LEFT JOIN mst_item mi ON mi.id = r.material_item_id
            LEFT JOIN mst_material m ON m.id = mi.material_id
            LEFT JOIN mst_component c ON c.id = r.component_id
            WHERE r.product_id = {$productId}
            ORDER BY r.sort_order, r.line_no
        ";
        $recipeRows = $this->dashboard_safe_query($sql);
        if (!$recipeRows) {
            $this->json_ok(['recipe' => []]);
            return;
        }

        // Kumpulkan kombinasi (material_id, division_id) dan (component_id, division_id) yang dibutuhkan.
        $matDivPairs  = []; // [materialId => [divId => [uomId, ...]]]
        $compDivPairs = []; // [componentId => [divId, ...]]
        $componentParentDiv = []; // [componentId => fallback divId from product recipe]
        foreach ($recipeRows->result_array() as $r) {
            $divId = (int)$r['source_division_id'] > 0 ? (int)$r['source_division_id'] : $defaultDivId;
            if ($r['line_type'] === 'MATERIAL' && !empty($r['material_id'])) {
                $matId = (int)$r['material_id'];
                $uomId = (int)($r['recipe_uom_id'] ?? 0);
                $matDivPairs[$matId][$divId][$uomId] = true;
            } elseif ($r['line_type'] === 'COMPONENT' && !empty($r['component_id'])) {
                $cmpId = (int)$r['component_id'];
                $compDivPairs[$cmpId][$divId] = true;
                $componentParentDiv[$cmpId] = $divId;
            }
        }

        // Untuk source BASE/PREPARE, tampilkan bahan pembentuknya juga.
        $formulaRowsByComponent = [];
        if ($this->dashboard_table_ready('mst_component_formula') && !empty($componentParentDiv)) {
            $cmpIds = array_map('intval', array_keys($componentParentDiv));
            $cmpIdList = implode(',', $cmpIds);
            $formulaMaterialExpr = $this->db->field_exists('material_id', 'mst_component_formula')
                ? 'COALESCE(f.material_id, mi.material_id)'
                : 'mi.material_id';
            $formulaSourceDivisionExpr = $this->db->field_exists('source_division_id', 'mst_component_formula')
                ? 'COALESCE(f.source_division_id, 0)'
                : '0';
            $formulaSql = "
                SELECT
                    f.id,
                    f.component_id AS parent_component_id,
                    f.line_type,
                    f.qty,
                    {$formulaSourceDivisionExpr} AS source_division_id,
                    {$formulaMaterialExpr} AS material_id,
                    COALESCE(m.content_uom_id, 0) AS material_uom_id,
                    f.material_item_id,
                    f.sub_component_id,
                    COALESCE(m.material_name, sc.component_name, 'Unknown') AS ingredient_name,
                    COALESCE(m.material_code, '') AS material_code,
                    COALESCE(sc.component_type, '') AS sub_component_type,
                    COALESCE(sc.operational_division_id, 0) AS sub_component_division_id,
                    COALESCE(pc.operational_division_id, 0) AS parent_component_division_id,
                    COALESCE(mu.code, cu.code, '') AS uom_code
                FROM mst_component_formula f
                LEFT JOIN mst_item mi ON mi.id = f.material_item_id
                LEFT JOIN mst_material m ON m.id = {$formulaMaterialExpr}
                LEFT JOIN mst_component sc ON sc.id = f.sub_component_id
                LEFT JOIN mst_component pc ON pc.id = f.component_id
                LEFT JOIN mst_uom mu ON mu.id = m.content_uom_id
                LEFT JOIN mst_uom cu ON cu.id = sc.uom_id
                WHERE f.component_id IN ({$cmpIdList})
                ORDER BY f.component_id, f.sort_order, f.line_no
            ";
            $formulaResult = $this->dashboard_safe_query($formulaSql);
            if ($formulaResult) {
                foreach ($formulaResult->result_array() as $fr) {
                    $parentComponentId = (int)($fr['parent_component_id'] ?? 0);
                    if ($parentComponentId <= 0) {
                        continue;
                    }

                    $parentDivId = (int)($componentParentDiv[$parentComponentId] ?? 0);
                    $formulaDivId = (int)($fr['source_division_id'] ?? 0);
                    if ($formulaDivId <= 0) {
                        $formulaDivId = (int)($fr['sub_component_division_id'] ?? 0);
                    }
                    if ($formulaDivId <= 0) {
                        $formulaDivId = (int)($fr['parent_component_division_id'] ?? 0);
                    }
                    if ($formulaDivId <= 0) {
                        $formulaDivId = $parentDivId;
                    }
                    if ($formulaDivId <= 0) {
                        $formulaDivId = $defaultDivId;
                    }

                    $fr['resolved_division_id'] = $formulaDivId;
                    $formulaRowsByComponent[$parentComponentId][] = $fr;

                    if (strtoupper((string)($fr['line_type'] ?? '')) === 'MATERIAL' && !empty($fr['material_id'])) {
                        $matDivPairs[(int)$fr['material_id']][$formulaDivId][(int)($fr['material_uom_id'] ?? 0)] = true;
                    } elseif (strtoupper((string)($fr['line_type'] ?? '')) === 'COMPONENT' && !empty($fr['sub_component_id'])) {
                        $compDivPairs[(int)$fr['sub_component_id']][$formulaDivId] = true;
                    }
                }
            }
        }

        // Query stok material per (material_id, division_id, content_uom_id) — sama dengan cache POS.
        $materialStockMap = []; // [matId][divId][uomId] => qty
        if ($hasMaterial && !empty($matDivPairs)) {
            $matIds = array_map('intval', array_keys($matDivPairs));
            $matIdList = implode(',', $matIds);
            $matSql = "
                SELECT
                    COALESCE(s.material_id, mi2.material_id) AS material_id,
                    s.division_id,
                    COALESCE(s.content_uom_id, 0) AS content_uom_id,
                    ROUND(SUM(s.closing_qty_content), 4) AS total_qty
                FROM inv_division_monthly_stock s
                LEFT JOIN mst_item mi2 ON mi2.id = s.item_id
                INNER JOIN (
                    SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
                    FROM inv_division_monthly_stock
                    WHERE month_key <= " . $this->db->escape($targetMonth) . "
                    GROUP BY division_id, destination_type, identity_key
                ) lm ON lm.division_id = s.division_id
                    AND lm.destination_type = s.destination_type
                    AND lm.identity_key = s.identity_key
                    AND lm.max_month = s.month_key
                WHERE COALESCE(s.material_id, mi2.material_id) IN ({$matIdList})
                GROUP BY COALESCE(s.material_id, mi2.material_id), s.division_id, COALESCE(s.content_uom_id, 0)
            ";
            $matResult = $this->dashboard_safe_query($matSql);
            if ($matResult) {
                foreach ($matResult->result_array() as $row) {
                    $materialStockMap[(int)$row['material_id']][(int)$row['division_id']][(int)($row['content_uom_id'] ?? 0)] = (float)$row['total_qty'];
                }
            }
        }

        // Query stok component per (component_id, division_id)
        $componentStockMap = []; // [compId][divId] => qty
        if ($hasComponent && !empty($compDivPairs)) {
            $cmpIds    = array_map('intval', array_keys($compDivPairs));
            $cmpIdList = implode(',', $cmpIds);
            $compSql = "
                SELECT s.component_id, s.division_id, ROUND(SUM(s.closing_qty), 4) AS total_qty
                FROM inv_component_monthly_stock s
                INNER JOIN (
                    SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
                    FROM inv_component_monthly_stock
                    WHERE month_key <= " . $this->db->escape($targetMonth) . "
                    GROUP BY location_type, division_id, component_id, uom_id
                ) lm ON lm.location_type = s.location_type
                    AND lm.division_id <=> s.division_id
                    AND lm.component_id = s.component_id
                    AND lm.uom_id = s.uom_id
                    AND lm.max_month = s.month_key
                WHERE s.component_id IN ({$cmpIdList})
                GROUP BY s.component_id, s.division_id
            ";
            $compResult = $this->dashboard_safe_query($compSql);
            if ($compResult) {
                foreach ($compResult->result_array() as $row) {
                    $componentStockMap[(int)$row['component_id']][(int)$row['division_id']] = (float)$row['total_qty'];
                }
            }
        }

        $recipe = [];
        foreach ($recipeRows->result_array() as $r) {
            // Tentukan division yang dipakai resep ini
            $divId   = (int)$r['source_division_id'] > 0 ? (int)$r['source_division_id'] : $defaultDivId;
            $stockQty = 0.0;

            if ($r['line_type'] === 'MATERIAL' && !empty($r['material_id'])) {
                $matId    = (int)$r['material_id'];
                $byDiv    = $materialStockMap[$matId] ?? [];
                $uomId    = (int)($r['recipe_uom_id'] ?? 0);
                if ($divId > 0 && isset($byDiv[$divId])) {
                    // Ambil stok dari division yang ditentukan resep
                    $stockQty = $uomId > 0
                        ? (float)($byDiv[$divId][$uomId] ?? 0)
                        : array_sum($byDiv[$divId]);
                } elseif ($divId <= 0) {
                    // Tidak ada filter division — jumlah semua (fallback kompatibilitas)
                    $stockQty = 0.0;
                    foreach ($byDiv as $uomBuckets) {
                        $stockQty += $uomId > 0 ? (float)($uomBuckets[$uomId] ?? 0) : array_sum($uomBuckets);
                    }
                }
                // Jika divId > 0 tapi tidak ada stok di division itu → 0 (benar)
            } elseif ($r['line_type'] === 'COMPONENT' && !empty($r['component_id'])) {
                $cmpId    = (int)$r['component_id'];
                $byDiv    = $componentStockMap[$cmpId] ?? [];
                if ($divId > 0 && isset($byDiv[$divId])) {
                    $stockQty = $byDiv[$divId];
                } elseif ($divId <= 0) {
                    $stockQty = array_sum($byDiv);
                }
            }

            $lineType      = strtoupper((string)$r['line_type']);
            $componentType = strtoupper((string)$r['component_type']);
            if ($lineType === 'MATERIAL') {
                $sourceType = 'bahan baku';
            } elseif ($lineType === 'COMPONENT' && $componentType === 'BASE') {
                $sourceType = 'base';
            } elseif ($lineType === 'COMPONENT' && $componentType === 'PREPARE') {
                $sourceType = 'prepare';
            } else {
                $sourceType = strtolower($lineType);
            }
            $children = [];
            if ($lineType === 'COMPONENT' && !empty($r['component_id'])) {
                $parentComponentId = (int)$r['component_id'];
                foreach (($formulaRowsByComponent[$parentComponentId] ?? []) as $fr) {
                    $childLineType = strtoupper((string)($fr['line_type'] ?? ''));
                    $childDivId = (int)($fr['resolved_division_id'] ?? 0);
                    $childStockQty = 0.0;
                    $childSourceType = strtolower($childLineType);

                    if ($childLineType === 'MATERIAL' && !empty($fr['material_id'])) {
                        $childMatId = (int)$fr['material_id'];
                        $byDiv = $materialStockMap[$childMatId] ?? [];
                        $childUomId = (int)($fr['material_uom_id'] ?? 0);
                        if ($childDivId > 0 && isset($byDiv[$childDivId])) {
                            $childStockQty = $childUomId > 0
                                ? (float)($byDiv[$childDivId][$childUomId] ?? 0)
                                : array_sum($byDiv[$childDivId]);
                        } elseif ($childDivId <= 0) {
                            foreach ($byDiv as $uomBuckets) {
                                $childStockQty += $childUomId > 0 ? (float)($uomBuckets[$childUomId] ?? 0) : array_sum($uomBuckets);
                            }
                        }
                        $childSourceType = 'bahan baku';
                    } elseif ($childLineType === 'COMPONENT' && !empty($fr['sub_component_id'])) {
                        $childCmpId = (int)$fr['sub_component_id'];
                        $byDiv = $componentStockMap[$childCmpId] ?? [];
                        if ($childDivId > 0 && isset($byDiv[$childDivId])) {
                            $childStockQty = (float)$byDiv[$childDivId];
                        } elseif ($childDivId <= 0) {
                            $childStockQty = array_sum($byDiv);
                        }

                        $subComponentType = strtoupper((string)($fr['sub_component_type'] ?? ''));
                        if ($subComponentType === 'BASE') {
                            $childSourceType = 'base';
                        } elseif ($subComponentType === 'PREPARE') {
                            $childSourceType = 'prepare';
                        } else {
                            $childSourceType = 'component';
                        }
                    }

                    $childQty = (float)($fr['qty'] ?? 0);
                    $children[] = [
                        'ingredient_name' => (string)($fr['ingredient_name'] ?? 'Unknown'),
                        'line_type'       => (string)($fr['line_type'] ?? ''),
                        'source_type'     => $childSourceType,
                        'qty_per_batch'   => $childQty,
                        'uom_code'        => (string)($fr['uom_code'] ?? ''),
                        'stock_qty'       => $childStockQty,
                        'available_batches' => $childQty > 0
                            ? max(0, floor($childStockQty / $childQty))
                            : null,
                        'division_id'     => $childDivId,
                        'is_bottleneck'   => $childStockQty <= 0,
                    ];
                }
            }

            $recipe[] = [
                'ingredient_name' => (string)$r['ingredient_name'],
                'line_type'       => (string)$r['line_type'],
                'ingredient_role' => (string)$r['ingredient_role'],
                'source_type'     => $sourceType,
                'qty_per_serve'   => (float)$r['qty'],
                'uom_code'        => (string)$r['uom_code'],
                'stock_qty'       => $stockQty,
                'available_servings' => (float)$r['qty'] > 0
                    ? max(0, floor($stockQty / (float)$r['qty']))
                    : null,
                'division_id'     => $divId,
                'is_bottleneck'   => $stockQty <= 0,
                'children'        => $children,
            ];
        }

        $this->json_ok(['recipe' => $recipe]);
    }

    private function dashboard_chart_filters(): array
    {
        return [
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-d'),
            'period_label' => 'Bulan Ini',
        ];
    }

    /**
     * Membaca peluang produksi dari stok bulan aktif saja. Analisa tidak
     * membuat batch, tidak memindahkan stok, dan tidak mengubah lot.
     *
     * Sebuah component masuk daftar bila:
     * - BASE/PREPARE masih kosong atau di bawah stok minimumnya; dan
     * - setidaknya ada satu bahan resep yang cukup untuk minimal dua batch.
     *
     * Dengan begitu daftar ini menyorot bahan yang berpotensi menumpuk, tanpa
     * menyimpulkan component siap diproses sebelum seluruh bahan dicek.
     */
    private function dashboard_component_production_suggestions(int $limit = 8, ?int $requestedDivisionId = null): array
    {
        $targetMonth = date('Y-m-01');
        $limit = max(1, min($limit, 250));
        $scopeDivisionId = $this->active_division_id();
        $divisionId = $scopeDivisionId !== null ? $scopeDivisionId : (int)$requestedDivisionId;

        $emptyResult = [
            'month_key' => $targetMonth,
            'summary' => [
                'empty_component_count' => 0,
                'critical_component_count' => 0,
                'suggestion_count' => 0,
                'ready_count' => 0,
                'waiting_count' => 0,
            ],
            'division_options' => [],
            'total_rows' => 0,
            'rows' => [],
        ];

        if (
            !$this->dashboard_table_ready('mst_component')
            || !$this->dashboard_table_ready('mst_component_formula')
            || !$this->dashboard_table_ready('inv_component_monthly_stock')
            || !$this->dashboard_table_ready('inv_division_monthly_stock')
        ) {
            return $emptyResult;
        }

        $divisionWhere = $divisionId > 0 ? ' AND c.operational_division_id = ' . (int)$divisionId : '';
        $componentResult = $this->dashboard_safe_query("
            SELECT
                c.id AS component_id,
                c.component_code,
                c.component_name,
                c.component_type,
                c.operational_division_id,
                c.min_stock,
                c.yield_qty,
                COALESCE(d.name, '-') AS division_name,
                COALESCE(d.code, '') AS division_code,
                COALESCE(u.code, '') AS uom_code
            FROM mst_component c
            LEFT JOIN mst_operational_division d ON d.id = c.operational_division_id
            LEFT JOIN mst_uom u ON u.id = c.uom_id
            WHERE COALESCE(c.is_active, 1) = 1
              AND c.component_type IN ('BASE', 'PREPARE')
              {$divisionWhere}
            ORDER BY d.name, c.component_type, c.component_name
        ");
        if (!$componentResult) {
            return $emptyResult;
        }

        $components = $componentResult->result_array();
        if (empty($components)) {
            return $emptyResult;
        }

        $componentIds = array_values(array_unique(array_map(static function (array $row): int {
            return (int)($row['component_id'] ?? 0);
        }, $components)));
        $componentIds = array_values(array_filter($componentIds));
        if (empty($componentIds)) {
            return $emptyResult;
        }

        $componentIdList = implode(',', $componentIds);
        $targetMonthSql = $this->db->escape($targetMonth);
        $componentStockResult = $this->dashboard_safe_query("
            SELECT
                s.component_id,
                COALESCE(s.division_id, 0) AS division_id,
                UPPER(COALESCE(s.location_type, '')) AS location_type,
                ROUND(SUM(COALESCE(s.closing_qty, 0)), 4) AS closing_qty
            FROM inv_component_monthly_stock s
            WHERE s.month_key = {$targetMonthSql}
              AND s.component_id IN ({$componentIdList})
            GROUP BY s.component_id, s.division_id, s.location_type
        ");

        $componentStockMap = [];
        if ($componentStockResult) {
            foreach ($componentStockResult->result_array() as $stockRow) {
                $componentId = (int)($stockRow['component_id'] ?? 0);
                $stockDivisionId = (int)($stockRow['division_id'] ?? 0);
                $locationType = strtoupper(trim((string)($stockRow['location_type'] ?? '')));
                if ($componentId <= 0 || $stockDivisionId <= 0 || $locationType === '') {
                    continue;
                }
                $componentStockMap[$componentId][$stockDivisionId][$locationType] = (float)($stockRow['closing_qty'] ?? 0);
            }
        }

        $divisionCodeMap = [];
        $candidateRows = [];
        $emptyComponentCount = 0;
        $criticalComponentCount = 0;
        foreach ($components as $component) {
            $componentId = (int)($component['component_id'] ?? 0);
            $componentDivisionId = (int)($component['operational_division_id'] ?? 0);
            if ($componentId <= 0 || $componentDivisionId <= 0) {
                continue;
            }

            $divisionCode = strtoupper(trim((string)($component['division_code'] ?? '')));
            $divisionCodeMap[$componentDivisionId] = $divisionCode;
            $defaultLocationType = $this->dashboard_component_suggestion_default_location($divisionCode);
            $stockByLocation = $componentStockMap[$componentId][$componentDivisionId] ?? [];
            $locationTypes = array_keys($stockByLocation);
            if ($defaultLocationType !== '' && !array_key_exists($defaultLocationType, $stockByLocation)) {
                $locationTypes[] = $defaultLocationType;
            }
            if (empty($locationTypes)) {
                continue;
            }

            $minimumStock = max(0.0, (float)($component['min_stock'] ?? 0));
            foreach (array_values(array_unique($locationTypes)) as $locationType) {
                $currentQty = (float)($stockByLocation[$locationType] ?? 0);
                if ($currentQty > $minimumStock + 0.0001) {
                    continue;
                }

                $stockState = $currentQty <= 0.0001 ? 'EMPTY' : 'CRITICAL';
                if ($stockState === 'EMPTY') {
                    $emptyComponentCount++;
                } else {
                    $criticalComponentCount++;
                }

                $candidateRows[] = [
                    'component_id' => $componentId,
                    'component_code' => (string)($component['component_code'] ?? ''),
                    'component_name' => (string)($component['component_name'] ?? '-'),
                    'component_type' => strtoupper((string)($component['component_type'] ?? 'PREPARE')),
                    'division_id' => $componentDivisionId,
                    'division_name' => (string)($component['division_name'] ?? '-'),
                    'division_code' => $divisionCode,
                    'location_type' => $locationType,
                    'location_label' => $this->dashboard_destination_label($locationType),
                    'current_qty' => $currentQty,
                    'min_stock' => $minimumStock,
                    'yield_qty' => max(0.0, (float)($component['yield_qty'] ?? 0)),
                    'uom_code' => (string)($component['uom_code'] ?? ''),
                    'stock_state' => $stockState,
                ];
            }
        }

        if (empty($candidateRows)) {
            $emptyResult['summary']['empty_component_count'] = $emptyComponentCount;
            $emptyResult['summary']['critical_component_count'] = $criticalComponentCount;
            return $emptyResult;
        }

        $candidateComponentIds = array_values(array_unique(array_map(static function (array $row): int {
            return (int)($row['component_id'] ?? 0);
        }, $candidateRows)));
        $candidateComponentIdList = implode(',', $candidateComponentIds);
        $formulaMaterialExpr = $this->db->field_exists('material_id', 'mst_component_formula')
            ? 'COALESCE(f.material_id, mi.material_id)'
            : 'mi.material_id';
        $formulaSourceDivisionExpr = $this->db->field_exists('source_division_id', 'mst_component_formula')
            ? 'NULLIF(f.source_division_id, 0)'
            : 'NULL';
        $formulaResult = $this->dashboard_safe_query("
            SELECT
                f.component_id,
                f.line_no,
                UPPER(COALESCE(f.line_type, '')) AS line_type,
                ROUND(COALESCE(f.qty, 0), 4) AS qty,
                COALESCE({$formulaSourceDivisionExpr}, c.operational_division_id) AS source_division_id,
                {$formulaMaterialExpr} AS material_id,
                COALESCE(m.material_name, '') AS material_name,
                COALESCE(m.content_uom_id, 0) AS material_uom_id,
                COALESCE(mu.code, '') AS material_uom_code,
                f.sub_component_id,
                COALESCE(sc.component_name, '') AS sub_component_name,
                COALESCE(sc.uom_id, 0) AS sub_component_uom_id,
                COALESCE(cu.code, '') AS sub_component_uom_code
            FROM mst_component_formula f
            INNER JOIN mst_component c ON c.id = f.component_id
            LEFT JOIN mst_item mi ON mi.id = f.material_item_id
            LEFT JOIN mst_material m ON m.id = {$formulaMaterialExpr}
            LEFT JOIN mst_uom mu ON mu.id = m.content_uom_id
            LEFT JOIN mst_component sc ON sc.id = f.sub_component_id
            LEFT JOIN mst_uom cu ON cu.id = sc.uom_id
            WHERE f.component_id IN ({$candidateComponentIdList})
            ORDER BY f.component_id, f.line_no, f.id
        ");

        $formulaByComponent = [];
        $materialIds = [];
        if ($formulaResult) {
            foreach ($formulaResult->result_array() as $formulaRow) {
                $formulaComponentId = (int)($formulaRow['component_id'] ?? 0);
                if ($formulaComponentId <= 0) {
                    continue;
                }
                $formulaByComponent[$formulaComponentId][] = $formulaRow;
                if (strtoupper((string)($formulaRow['line_type'] ?? '')) === 'MATERIAL') {
                    $materialId = (int)($formulaRow['material_id'] ?? 0);
                    if ($materialId > 0) {
                        $materialIds[$materialId] = true;
                    }
                }
            }
        }

        if (empty($formulaByComponent) || empty($materialIds)) {
            $emptyResult['summary']['empty_component_count'] = $emptyComponentCount;
            $emptyResult['summary']['critical_component_count'] = $criticalComponentCount;
            return $emptyResult;
        }

        $materialIdList = implode(',', array_keys($materialIds));
        $materialStockResult = $this->dashboard_safe_query("
            SELECT
                COALESCE(s.material_id, mi.material_id) AS material_id,
                s.division_id,
                UPPER(COALESCE(s.destination_type, '')) AS destination_type,
                COALESCE(s.content_uom_id, 0) AS content_uom_id,
                ROUND(SUM(COALESCE(s.closing_qty_content, 0)), 4) AS closing_qty,
                ROUND(SUM(COALESCE(s.total_value, 0)), 2) AS total_value
            FROM inv_division_monthly_stock s
            LEFT JOIN mst_item mi ON mi.id = s.item_id
            WHERE s.month_key = {$targetMonthSql}
              AND COALESCE(s.material_id, mi.material_id) IN ({$materialIdList})
            GROUP BY COALESCE(s.material_id, mi.material_id), s.division_id, s.destination_type, s.content_uom_id
        ");

        $materialStockMap = [];
        if ($materialStockResult) {
            foreach ($materialStockResult->result_array() as $stockRow) {
                $materialId = (int)($stockRow['material_id'] ?? 0);
                $stockDivisionId = (int)($stockRow['division_id'] ?? 0);
                $destinationType = strtoupper(trim((string)($stockRow['destination_type'] ?? '')));
                $uomId = (int)($stockRow['content_uom_id'] ?? 0);
                if ($materialId <= 0 || $stockDivisionId <= 0 || $destinationType === '') {
                    continue;
                }
                $materialStockMap[$materialId][$stockDivisionId][$destinationType][$uomId] = [
                    'qty' => (float)($stockRow['closing_qty'] ?? 0),
                    'total_value' => (float)($stockRow['total_value'] ?? 0),
                ];
            }
        }

        $suggestionRows = [];
        $readyCount = 0;
        $waitingCount = 0;
        foreach ($candidateRows as $candidate) {
            $formulaRows = $formulaByComponent[(int)$candidate['component_id']] ?? [];
            if (empty($formulaRows)) {
                continue;
            }

            $ingredients = [];
            $piledIngredients = [];
            $blockingIngredients = [];
            $maxPossibleBatches = null;
            $validIngredientCount = 0;
            foreach ($formulaRows as $formulaRow) {
                $lineType = strtoupper(trim((string)($formulaRow['line_type'] ?? '')));
                $qtyPerBatch = max(0.0, (float)($formulaRow['qty'] ?? 0));
                $sourceDivisionId = (int)($formulaRow['source_division_id'] ?? 0);
                if ($sourceDivisionId <= 0) {
                    $sourceDivisionId = (int)$candidate['division_id'];
                }
                $sourceLocationType = $this->dashboard_component_suggestion_source_location(
                    $sourceDivisionId,
                    (int)$candidate['division_id'],
                    (string)$candidate['location_type'],
                    $divisionCodeMap
                );

                $ingredientName = '-';
                $uomCode = '';
                $stockQty = 0.0;
                $stockValue = 0.0;
                $isMaterial = $lineType === 'MATERIAL';
                $configValid = $qtyPerBatch > 0;
                if ($isMaterial) {
                    $materialId = (int)($formulaRow['material_id'] ?? 0);
                    $ingredientName = (string)($formulaRow['material_name'] ?? 'Bahan baku belum dipetakan');
                    $uomCode = (string)($formulaRow['material_uom_code'] ?? '');
                    $uomId = (int)($formulaRow['material_uom_id'] ?? 0);
                    if ($materialId <= 0 || $sourceLocationType === '') {
                        $configValid = false;
                    } else {
                        $stockByUom = $materialStockMap[$materialId][$sourceDivisionId][$sourceLocationType] ?? [];
                        if ($uomId > 0) {
                            $stockBucket = (array)($stockByUom[$uomId] ?? []);
                            $stockQty = (float)($stockBucket['qty'] ?? 0);
                            $stockValue = (float)($stockBucket['total_value'] ?? 0);
                        } else {
                            foreach ($stockByUom as $stockBucket) {
                                $stockQty += (float)($stockBucket['qty'] ?? 0);
                                $stockValue += (float)($stockBucket['total_value'] ?? 0);
                            }
                        }
                    }
                } elseif ($lineType === 'COMPONENT') {
                    $subComponentId = (int)($formulaRow['sub_component_id'] ?? 0);
                    $ingredientName = (string)($formulaRow['sub_component_name'] ?? 'Component belum dipetakan');
                    $uomCode = (string)($formulaRow['sub_component_uom_code'] ?? '');
                    if ($subComponentId <= 0 || $sourceLocationType === '') {
                        $configValid = false;
                    } else {
                        $stockQty = (float)($componentStockMap[$subComponentId][$sourceDivisionId][$sourceLocationType] ?? 0);
                    }
                } else {
                    $configValid = false;
                    $ingredientName = 'Baris formula belum lengkap';
                }

                $availableBatches = $configValid
                    ? max(0, (int)floor($stockQty / max($qtyPerBatch, 0.0001)))
                    : 0;
                if ($configValid) {
                    $validIngredientCount++;
                    $maxPossibleBatches = $maxPossibleBatches === null
                        ? $availableBatches
                        : min($maxPossibleBatches, $availableBatches);
                }

                $isPiled = $isMaterial && $availableBatches >= 2;
                $isBlocking = !$configValid || $availableBatches < 1;
                $ingredient = [
                    'name' => $ingredientName,
                    'line_type' => $lineType,
                    'source_division_id' => $sourceDivisionId,
                    'source_location_type' => $sourceLocationType,
                    'qty_per_batch' => $qtyPerBatch,
                    'stock_qty' => $stockQty,
                    'stock_value' => $stockValue,
                    'uom_code' => $uomCode,
                    'available_batches' => $availableBatches,
                    'is_piled' => $isPiled,
                    'is_blocking' => $isBlocking,
                    'config_valid' => $configValid,
                ];
                $ingredients[] = $ingredient;
                if ($isPiled) {
                    $piledIngredients[] = $ingredient;
                }
                if ($isBlocking) {
                    $blockingIngredients[] = $ingredient;
                }
            }

            // Fokus halaman ini adalah bahan yang dapat ditindaklanjuti, bukan seluruh daftar stok kritis.
            if (empty($piledIngredients) || $validIngredientCount <= 0) {
                continue;
            }

            usort($piledIngredients, static function (array $left, array $right): int {
                $valueComparison = (float)($right['stock_value'] ?? 0) <=> (float)($left['stock_value'] ?? 0);
                if ($valueComparison !== 0) {
                    return $valueComparison;
                }
                return (int)$right['available_batches'] <=> (int)$left['available_batches'];
            });

            $maxPossibleBatches = (int)($maxPossibleBatches ?? 0);
            $isReady = empty($blockingIngredients) && $maxPossibleBatches >= 1;
            $yieldQty = max(0.0001, (float)$candidate['yield_qty']);
            $neededOutput = max($yieldQty, (float)$candidate['min_stock'] - (float)$candidate['current_qty']);
            $neededBatches = max(1, (int)ceil($neededOutput / $yieldQty));
            $recommendedBatches = $isReady ? min($neededBatches, $maxPossibleBatches) : 0;

            if ($isReady) {
                $readyCount++;
            } else {
                $waitingCount++;
            }

            $candidate['ingredients'] = $ingredients;
            $candidate['piled_ingredients'] = $piledIngredients;
            $candidate['blocking_ingredients'] = $blockingIngredients;
            $candidate['formula_line_count'] = count($ingredients);
            $candidate['ready_line_count'] = count($ingredients) - count($blockingIngredients);
            $candidate['max_possible_batches'] = $maxPossibleBatches;
            $candidate['recommended_batches'] = $recommendedBatches;
            $candidate['production_state'] = $isReady ? 'READY' : 'WAITING_INPUT';
            $candidate['production_state_label'] = $isReady ? 'Siap diproses' : 'Perlu bahan pendukung';
            $suggestionRows[] = $candidate;
        }

        usort($suggestionRows, static function (array $left, array $right): int {
            $leftReady = ($left['production_state'] ?? '') === 'READY' ? 1 : 0;
            $rightReady = ($right['production_state'] ?? '') === 'READY' ? 1 : 0;
            if ($leftReady !== $rightReady) {
                return $rightReady <=> $leftReady;
            }
            $leftEmpty = ($left['stock_state'] ?? '') === 'EMPTY' ? 1 : 0;
            $rightEmpty = ($right['stock_state'] ?? '') === 'EMPTY' ? 1 : 0;
            if ($leftEmpty !== $rightEmpty) {
                return $rightEmpty <=> $leftEmpty;
            }
            return strcasecmp((string)($left['component_name'] ?? ''), (string)($right['component_name'] ?? ''));
        });

        $divisionOptions = [];
        foreach ($suggestionRows as $row) {
            $rowDivisionId = (int)($row['division_id'] ?? 0);
            if ($rowDivisionId > 0) {
                $divisionOptions[$rowDivisionId] = (string)($row['division_name'] ?? '-');
            }
        }
        asort($divisionOptions);

        $totalRows = count($suggestionRows);
        return [
            'month_key' => $targetMonth,
            'summary' => [
                'empty_component_count' => $emptyComponentCount,
                'critical_component_count' => $criticalComponentCount,
                'suggestion_count' => $totalRows,
                'ready_count' => $readyCount,
                'waiting_count' => $waitingCount,
            ],
            'division_options' => $divisionOptions,
            'total_rows' => $totalRows,
            'rows' => array_slice($suggestionRows, 0, $limit),
        ];
    }

    private function dashboard_component_suggestion_default_location(string $divisionCode): string
    {
        $divisionCode = strtoupper(trim($divisionCode));
        return in_array($divisionCode, ['BAR', 'KITCHEN', 'ROASTERY'], true) ? $divisionCode : '';
    }

    private function dashboard_component_suggestion_source_location(
        int $sourceDivisionId,
        int $parentDivisionId,
        string $parentLocationType,
        array $divisionCodeMap
    ): string {
        if ($sourceDivisionId === $parentDivisionId && $parentLocationType !== '') {
            return strtoupper($parentLocationType);
        }

        return $this->dashboard_component_suggestion_default_location((string)($divisionCodeMap[$sourceDivisionId] ?? ''));
    }

    private function dashboard_stock_product_live(): array
    {
        if (!$this->dashboard_table_ready('pos_product_availability_cache') || !$this->dashboard_table_ready('mst_product')) {
            return ['summary' => [], 'rows' => []];
        }

        $summaryRows = $this->dashboard_safe_query("
            SELECT
                pac.availability_status,
                pd.name AS division_name,
                COUNT(*) AS total
            FROM pos_product_availability_cache pac
            INNER JOIN mst_product p ON p.id = pac.product_id
            INNER JOIN mst_product_category pc ON pc.id = p.product_category_id
            INNER JOIN mst_product_division pd ON pd.id = pc.product_division_id
            WHERE pd.name != 'EVENT'
              AND p.is_active = 1
            GROUP BY pac.availability_status, pd.name
            ORDER BY pd.name, pac.availability_status
        ");

        $summary = ['total' => 0, 'out' => 0, 'limited' => 0, 'available' => 0, 'by_division' => []];
        if ($summaryRows) {
            foreach ($summaryRows->result_array() as $r) {
                $div = (string)($r['division_name'] ?? 'LAIN');
                $status = strtoupper((string)($r['availability_status'] ?? ''));
                $cnt = (int)$r['total'];
                $summary['total'] += $cnt;
                if ($status === 'OUT') {
                    $summary['out'] += $cnt;
                } elseif ($status === 'LIMITED') {
                    $summary['limited'] += $cnt;
                } elseif ($status === 'AVAILABLE') {
                    $summary['available'] += $cnt;
                }
                if (!isset($summary['by_division'][$div])) {
                    $summary['by_division'][$div] = ['out' => 0, 'limited' => 0, 'available' => 0];
                }
                $summary['by_division'][$div][$status === 'OUT' ? 'out' : ($status === 'LIMITED' ? 'limited' : 'available')] += $cnt;
            }
        }

        $productRows = $this->dashboard_safe_query("
            SELECT
                pac.product_id,
                p.product_name,
                pd.name AS division_name,
                pc.name AS category_name,
                pac.availability_status,
                ROUND(pac.estimated_available_qty, 2) AS qty,
                COALESCE(u.code, '') AS uom_code,
                pac.bottleneck_name_snapshot,
                pac.bottleneck_kind,
                pac.hpp_live_snapshot,
                pac.is_dirty
            FROM pos_product_availability_cache pac
            INNER JOIN mst_product p ON p.id = pac.product_id
            INNER JOIN mst_product_category pc ON pc.id = p.product_category_id
            INNER JOIN mst_product_division pd ON pd.id = pc.product_division_id
            LEFT JOIN mst_uom u ON u.id = p.uom_id
            WHERE pd.name != 'EVENT'
              AND p.is_active = 1
            ORDER BY
                FIELD(pac.availability_status, 'OUT', 'LIMITED', 'AVAILABLE'),
                pac.estimated_available_qty ASC,
                pd.name,
                pc.name,
                p.product_name
            LIMIT 300
        ");

        $rows = $productRows ? $productRows->result_array() : [];
        return ['summary' => $summary, 'rows' => $rows];
    }

    private function dashboard_stagnant_lot_analysis(): array
    {
        $thresholdDays = 15;
        $monthStart = date('Y-m-01');
        $today = date('Y-m-d');
        $monthStartSql = $this->db->escape($monthStart);
        $todaySql = $this->db->escape($today);
        $materialLotCutoffWhere = "AND l.receipt_date >= {$monthStartSql} AND l.receipt_date <= {$todaySql}";
        $componentLotCutoffWhere = "AND l.receipt_date >= {$monthStartSql} AND l.receipt_date <= {$todaySql}";
        $data = [
            'threshold_days' => $thresholdDays,
            'material' => ['multi_lot' => [], 'aged_lot' => []],
            'component' => ['multi_lot' => [], 'aged_lot' => []],
            'summary' => [
                'material_multi_lot' => 0,
                'material_aged_lot' => 0,
                'component_multi_lot' => 0,
                'component_aged_lot' => 0,
            ],
        ];

        if ($this->dashboard_table_ready('inv_material_fifo_lot')) {
            $materialMulti = $this->dashboard_safe_query("
                SELECT
                    'material' AS source_type,
                    COALESCE(NULLIF(MAX(m.material_name), ''), NULLIF(MAX(i.item_name), ''), CONCAT('Material #', MAX(l.material_id))) AS item_name,
                    COALESCE(NULLIF(MAX(m.material_code), ''), NULLIF(MAX(i.item_code), ''), '') AS item_code,
                    CASE
                        WHEN l.location_scope = 'WAREHOUSE' THEN 'Gudang'
                        ELSE CONCAT(COALESCE(MAX(d.name), CONCAT('Divisi #', l.division_id)), ' · ', COALESCE(l.destination_type, 'REGULER'))
                    END AS location_label,
                    CASE
                        WHEN l.location_scope = 'WAREHOUSE' THEN 'GUDANG'
                        WHEN COALESCE(l.destination_type, '') = 'BAR' THEN 'BAR_REGULER'
                        WHEN COALESCE(l.destination_type, '') = 'BAR_EVENT' THEN 'BAR_EVENT'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN' THEN 'KITCHEN_REGULER'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN_EVENT' THEN 'KITCHEN_EVENT'
                        ELSE CONCAT('LAIN_', COALESCE(l.destination_type, 'OTHER'))
                    END AS location_bucket,
                    CASE
                        WHEN l.location_scope = 'WAREHOUSE' THEN 'Gudang'
                        WHEN COALESCE(l.destination_type, '') = 'BAR' THEN 'BAR Reguler'
                        WHEN COALESCE(l.destination_type, '') = 'BAR_EVENT' THEN 'BAR Event'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN' THEN 'Kitchen Reguler'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN_EVENT' THEN 'Kitchen Event'
                        ELSE CONCAT(COALESCE(MAX(d.name), 'Lainnya'), ' · ', COALESCE(l.destination_type, 'OTHER'))
                    END AS location_bucket_label,
                    COALESCE(MAX(u.code), '') AS uom_code,
                    l.location_scope,
                    COALESCE(l.division_id, 0) AS division_id,
                    COALESCE(l.destination_type, '') AS destination_type,
                    COALESCE(l.item_id, 0) AS item_id,
                    COALESCE(l.material_id, i.material_id, 0) AS material_id,
                    COALESCE(l.content_uom_id, 0) AS uom_id,
                    COUNT(*) AS lot_count,
                    MIN(l.receipt_date) AS oldest_receipt_date,
                    MAX(DATEDIFF(CURDATE(), l.receipt_date)) AS oldest_age_days,
                    ROUND(SUM(l.qty_balance), 4) AS qty_balance,
                    ROUND(SUM(l.qty_balance * l.unit_cost), 2) AS total_value
                FROM inv_material_fifo_lot l
                LEFT JOIN mst_item i ON i.id = l.item_id
                LEFT JOIN mst_material m ON m.id = COALESCE(l.material_id, i.material_id)
                LEFT JOIN mst_uom u ON u.id = l.content_uom_id
                LEFT JOIN mst_operational_division d ON d.id = l.division_id
                WHERE l.status = 'OPEN'
                  AND l.qty_balance > 0
                  {$materialLotCutoffWhere}
                GROUP BY
                    l.location_scope, COALESCE(l.division_id, 0), COALESCE(l.destination_type, ''),
                    COALESCE(l.item_id, 0), COALESCE(l.material_id, i.material_id, 0), COALESCE(l.content_uom_id, 0)
                HAVING COUNT(*) > 1
                ORDER BY COUNT(*) DESC, MIN(l.receipt_date) ASC, item_name ASC
                LIMIT 50
            ");
            $data['material']['multi_lot'] = $materialMulti ? $materialMulti->result_array() : [];
            foreach ($data['material']['multi_lot'] as &$row) {
                $row['lots'] = $this->dashboard_material_lot_detail_rows($row);
            }
            unset($row);

            $materialAged = $this->dashboard_safe_query("
                SELECT
                    'material' AS source_type,
                    l.id AS lot_id,
                    l.lot_no,
                    l.receipt_date,
                    DATEDIFF(CURDATE(), l.receipt_date) AS age_days,
                    COALESCE(NULLIF(m.material_name, ''), NULLIF(i.item_name, ''), CONCAT('Material #', l.material_id)) AS item_name,
                    COALESCE(NULLIF(m.material_code, ''), NULLIF(i.item_code, ''), '') AS item_code,
                    CASE
                        WHEN l.location_scope = 'WAREHOUSE' THEN 'Gudang'
                        ELSE CONCAT(COALESCE(d.name, CONCAT('Divisi #', l.division_id)), ' · ', COALESCE(l.destination_type, 'REGULER'))
                    END AS location_label,
                    CASE
                        WHEN l.location_scope = 'WAREHOUSE' THEN 'GUDANG'
                        WHEN COALESCE(l.destination_type, '') = 'BAR' THEN 'BAR_REGULER'
                        WHEN COALESCE(l.destination_type, '') = 'BAR_EVENT' THEN 'BAR_EVENT'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN' THEN 'KITCHEN_REGULER'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN_EVENT' THEN 'KITCHEN_EVENT'
                        ELSE CONCAT('LAIN_', COALESCE(l.destination_type, 'OTHER'))
                    END AS location_bucket,
                    CASE
                        WHEN l.location_scope = 'WAREHOUSE' THEN 'Gudang'
                        WHEN COALESCE(l.destination_type, '') = 'BAR' THEN 'BAR Reguler'
                        WHEN COALESCE(l.destination_type, '') = 'BAR_EVENT' THEN 'BAR Event'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN' THEN 'Kitchen Reguler'
                        WHEN COALESCE(l.destination_type, '') = 'KITCHEN_EVENT' THEN 'Kitchen Event'
                        ELSE CONCAT(COALESCE(d.name, 'Lainnya'), ' · ', COALESCE(l.destination_type, 'OTHER'))
                    END AS location_bucket_label,
                    COALESCE(u.code, '') AS uom_code,
                    COALESCE(l.material_id, i.material_id, 0) AS material_id,
                    ROUND(l.qty_balance, 4) AS qty_balance,
                    ROUND(l.unit_cost, 6) AS unit_cost,
                    ROUND(l.qty_balance * l.unit_cost, 2) AS total_value
                FROM inv_material_fifo_lot l
                LEFT JOIN mst_item i ON i.id = l.item_id
                LEFT JOIN mst_material m ON m.id = COALESCE(l.material_id, i.material_id)
                LEFT JOIN mst_uom u ON u.id = l.content_uom_id
                LEFT JOIN mst_operational_division d ON d.id = l.division_id
                WHERE l.status = 'OPEN'
                  AND l.qty_balance > 0
                  {$materialLotCutoffWhere}
                  AND l.receipt_date <= DATE_SUB(CURDATE(), INTERVAL {$thresholdDays} DAY)
                ORDER BY l.receipt_date ASC, total_value DESC, item_name ASC
                LIMIT 80
            ");
            $data['material']['aged_lot'] = $materialAged ? $materialAged->result_array() : [];
        }

        if ($this->dashboard_table_ready('inv_component_lot')) {
            $componentMulti = $this->dashboard_safe_query("
                SELECT
                    'component' AS source_type,
                    COALESCE(NULLIF(MAX(c.component_name), ''), CONCAT('Component #', MAX(l.component_id))) AS item_name,
                    COALESCE(NULLIF(MAX(c.component_code), ''), '') AS item_code,
                    CONCAT(COALESCE(MAX(d.name), MAX(l.location_type)), ' · ', COALESCE(MAX(l.location_type), 'REGULER')) AS location_label,
                    CASE
                        WHEN COALESCE(l.location_type, '') = 'BAR' THEN 'BAR_REGULER'
                        WHEN COALESCE(l.location_type, '') = 'BAR_EVENT' THEN 'BAR_EVENT'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN' THEN 'KITCHEN_REGULER'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN_EVENT' THEN 'KITCHEN_EVENT'
                        ELSE CONCAT('LAIN_', COALESCE(l.location_type, 'OTHER'))
                    END AS location_bucket,
                    CASE
                        WHEN COALESCE(l.location_type, '') = 'BAR' THEN 'BAR Reguler'
                        WHEN COALESCE(l.location_type, '') = 'BAR_EVENT' THEN 'BAR Event'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN' THEN 'Kitchen Reguler'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN_EVENT' THEN 'Kitchen Event'
                        ELSE CONCAT(COALESCE(MAX(d.name), 'Lainnya'), ' · ', COALESCE(l.location_type, 'OTHER'))
                    END AS location_bucket_label,
                    COALESCE(MAX(u.code), '') AS uom_code,
                    COALESCE(l.location_type, '') AS location_type,
                    COALESCE(l.division_id, 0) AS division_id,
                    l.component_id,
                    l.uom_id,
                    COUNT(*) AS lot_count,
                    MIN(l.receipt_date) AS oldest_receipt_date,
                    MAX(DATEDIFF(CURDATE(), l.receipt_date)) AS oldest_age_days,
                    ROUND(SUM(l.qty_balance), 4) AS qty_balance,
                    ROUND(SUM(l.qty_balance * l.unit_cost), 2) AS total_value
                FROM inv_component_lot l
                LEFT JOIN mst_component c ON c.id = l.component_id
                LEFT JOIN mst_uom u ON u.id = l.uom_id
                LEFT JOIN mst_operational_division d ON d.id = l.division_id
                WHERE UPPER(l.status) = 'OPEN'
                  AND l.qty_balance > 0
                  {$componentLotCutoffWhere}
                GROUP BY COALESCE(l.location_type, ''), COALESCE(l.division_id, 0), l.component_id, l.uom_id
                HAVING COUNT(*) > 1
                ORDER BY COUNT(*) DESC, MIN(l.receipt_date) ASC, item_name ASC
                LIMIT 50
            ");
            $data['component']['multi_lot'] = $componentMulti ? $componentMulti->result_array() : [];
            foreach ($data['component']['multi_lot'] as &$row) {
                $row['lots'] = $this->dashboard_component_lot_detail_rows($row);
            }
            unset($row);

            $componentAged = $this->dashboard_safe_query("
                SELECT
                    'component' AS source_type,
                    l.id AS lot_id,
                    l.lot_no,
                    l.receipt_date,
                    DATEDIFF(CURDATE(), l.receipt_date) AS age_days,
                    COALESCE(NULLIF(c.component_name, ''), CONCAT('Component #', l.component_id)) AS item_name,
                    COALESCE(NULLIF(c.component_code, ''), '') AS item_code,
                    CONCAT(COALESCE(d.name, l.location_type), ' · ', COALESCE(l.location_type, 'REGULER')) AS location_label,
                    CASE
                        WHEN COALESCE(l.location_type, '') = 'BAR' THEN 'BAR_REGULER'
                        WHEN COALESCE(l.location_type, '') = 'BAR_EVENT' THEN 'BAR_EVENT'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN' THEN 'KITCHEN_REGULER'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN_EVENT' THEN 'KITCHEN_EVENT'
                        ELSE CONCAT('LAIN_', COALESCE(l.location_type, 'OTHER'))
                    END AS location_bucket,
                    CASE
                        WHEN COALESCE(l.location_type, '') = 'BAR' THEN 'BAR Reguler'
                        WHEN COALESCE(l.location_type, '') = 'BAR_EVENT' THEN 'BAR Event'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN' THEN 'Kitchen Reguler'
                        WHEN COALESCE(l.location_type, '') = 'KITCHEN_EVENT' THEN 'Kitchen Event'
                        ELSE CONCAT(COALESCE(d.name, 'Lainnya'), ' · ', COALESCE(l.location_type, 'OTHER'))
                    END AS location_bucket_label,
                    COALESCE(u.code, '') AS uom_code,
                    l.component_id,
                    ROUND(l.qty_balance, 4) AS qty_balance,
                    ROUND(l.unit_cost, 6) AS unit_cost,
                    ROUND(l.qty_balance * l.unit_cost, 2) AS total_value
                FROM inv_component_lot l
                LEFT JOIN mst_component c ON c.id = l.component_id
                LEFT JOIN mst_uom u ON u.id = l.uom_id
                LEFT JOIN mst_operational_division d ON d.id = l.division_id
                WHERE UPPER(l.status) = 'OPEN'
                  AND l.qty_balance > 0
                  {$componentLotCutoffWhere}
                  AND l.receipt_date <= DATE_SUB(CURDATE(), INTERVAL {$thresholdDays} DAY)
                ORDER BY l.receipt_date ASC, total_value DESC, item_name ASC
                LIMIT 80
            ");
            $data['component']['aged_lot'] = $componentAged ? $componentAged->result_array() : [];
        }

        $data['summary']['material_multi_lot'] = count($data['material']['multi_lot']);
        $data['summary']['material_aged_lot'] = count($data['material']['aged_lot']);
        $data['summary']['component_multi_lot'] = count($data['component']['multi_lot']);
        $data['summary']['component_aged_lot'] = count($data['component']['aged_lot']);

        return $data;
    }

    private function dashboard_material_lot_detail_rows(array $group): array
    {
        $monthKey = date('Y-m-01');
        $today = date('Y-m-d');
        $monthKeySql = $this->db->escape($monthKey);
        $todaySql = $this->db->escape($today);
        $query = $this->db
            ->select("'material' AS source_type", false)
            ->select('l.id AS lot_id, l.lot_no, l.receipt_date, l.expiry_date, DATEDIFF(CURDATE(), l.receipt_date) AS age_days', false)
            ->select('l.location_scope, COALESCE(l.division_id, 0) AS division_id, COALESCE(l.destination_type, "") AS destination_type', false)
            ->select('COALESCE(l.item_id, 0) AS item_id, COALESCE(l.material_id, i.material_id, 0) AS material_id', false)
            ->select('COALESCE(l.buy_uom_id, c.buy_uom_id, 0) AS buy_uom_id, COALESCE(l.content_uom_id, c.content_uom_id, 0) AS content_uom_id', false)
            ->select('COALESCE(l.profile_key, "") AS profile_key, COALESCE(NULLIF(c.catalog_name, ""), NULLIF(m.material_name, ""), NULLIF(i.item_name, ""), CONCAT("Lot #", l.id)) AS profile_name', false)
            ->select('COALESCE(c.brand_name, "") AS profile_brand, COALESCE(c.line_description, "") AS profile_description', false)
            ->select('COALESCE(c.content_per_buy, 1) AS profile_content_per_buy, COALESCE(bu.code, "") AS profile_buy_uom_code, COALESCE(cu.code, "") AS profile_content_uom_code', false)
            ->select('COALESCE(l.expiry_date, "0000-00-00") AS profile_expired_date', false)
            ->select('COALESCE(NULLIF(m.material_name, ""), NULLIF(i.item_name, ""), CONCAT("Material #", COALESCE(l.material_id, i.material_id))) AS item_name', false)
            ->select('COALESCE(NULLIF(m.material_code, ""), NULLIF(i.item_code, ""), "") AS item_code', false)
            ->select('COALESCE(cu.code, "") AS uom_code, ROUND(l.qty_balance, 4) AS qty_balance, ROUND(l.unit_cost, 6) AS unit_cost, ROUND(l.qty_balance * l.unit_cost, 2) AS total_value', false)
            ->select("CASE WHEN l.location_scope = 'DIVISION' AND l.receipt_date >= {$monthKeySql} AND l.receipt_date <= {$todaySql} THEN 1 ELSE 0 END AS can_recon_adjust", false)
            ->select("(SELECT COALESCE(SUM(ms.closing_qty_content), 0)
                FROM inv_division_monthly_stock ms
                WHERE ms.month_key = {$monthKeySql}
                  AND COALESCE(ms.division_id, 0) = COALESCE(l.division_id, 0)
                  AND COALESCE(ms.destination_type, '') = COALESCE(l.destination_type, '')
                  AND COALESCE(ms.item_id, 0) = COALESCE(l.item_id, 0)
                  AND COALESCE(ms.material_id, 0) = COALESCE(l.material_id, i.material_id, 0)
                  AND COALESCE(ms.content_uom_id, 0) = COALESCE(l.content_uom_id, 0)
                  AND COALESCE(ms.profile_key, '') = COALESCE(l.profile_key, '')
            ) AS profile_system_qty_content", false)
            ->from('inv_material_fifo_lot l')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = COALESCE(l.material_id, i.material_id)', 'left')
            ->join('mst_purchase_catalog c', 'c.profile_key = l.profile_key', 'left')
            ->join('mst_uom bu', 'bu.id = COALESCE(l.buy_uom_id, c.buy_uom_id)', 'left')
            ->join('mst_uom cu', 'cu.id = COALESCE(l.content_uom_id, c.content_uom_id)', 'left')
            ->where('l.status', 'OPEN')
            ->where('l.qty_balance >', 0)
            ->where('l.location_scope', (string)($group['location_scope'] ?? 'DIVISION'))
            ->where('COALESCE(l.division_id, 0) = ' . (int)($group['division_id'] ?? 0), null, false)
            ->where('COALESCE(l.destination_type, "") = ' . $this->db->escape((string)($group['destination_type'] ?? '')), null, false)
            ->where('COALESCE(l.item_id, 0) = ' . (int)($group['item_id'] ?? 0), null, false)
            ->where('COALESCE(l.material_id, i.material_id, 0) = ' . (int)($group['material_id'] ?? 0), null, false)
            ->where('COALESCE(l.content_uom_id, 0) = ' . (int)($group['uom_id'] ?? 0), null, false)
            ->where("l.receipt_date >= {$monthKeySql} AND l.receipt_date <= {$todaySql}", null, false)
            ->order_by('l.receipt_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->limit(120)
            ->get();

        return $query ? $query->result_array() : [];
    }

    private function dashboard_component_lot_detail_rows(array $group): array
    {
        $monthKey = date('Y-m-01');
        $today = date('Y-m-d');
        $monthKeySql = $this->db->escape($monthKey);
        $todaySql = $this->db->escape($today);
        $query = $this->db
            ->select("'component' AS source_type", false)
            ->select('l.id AS lot_id, l.lot_no, l.receipt_date, l.expiry_date, DATEDIFF(CURDATE(), l.receipt_date) AS age_days', false)
            ->select('l.location_type, COALESCE(l.division_id, 0) AS division_id, COALESCE(d.code, l.location_type, "") AS division_code', false)
            ->select('l.component_id, l.uom_id, COALESCE(NULLIF(c.component_name, ""), CONCAT("Component #", l.component_id)) AS item_name', false)
            ->select('COALESCE(NULLIF(c.component_code, ""), "") AS item_code, COALESCE(u.code, "") AS uom_code', false)
            ->select('ROUND(l.qty_balance, 4) AS qty_balance, ROUND(l.unit_cost, 6) AS unit_cost, ROUND(l.qty_balance * l.unit_cost, 2) AS total_value', false)
            ->select("CASE WHEN l.receipt_date >= {$monthKeySql} AND l.receipt_date <= {$todaySql} THEN 1 ELSE 0 END AS can_recon_adjust", false)
            ->select("(SELECT COALESCE(SUM(ms.closing_qty), 0)
                FROM inv_component_monthly_stock ms
                WHERE ms.month_key = {$monthKeySql}
                  AND COALESCE(ms.division_id, 0) = COALESCE(l.division_id, 0)
                  AND COALESCE(ms.location_type, '') = COALESCE(l.location_type, '')
                  AND ms.component_id = l.component_id
                  AND ms.uom_id = l.uom_id
            ) AS profile_system_qty", false)
            ->from('inv_component_lot l')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_operational_division d', 'd.id = l.division_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where("UPPER(l.status) = 'OPEN'", null, false)
            ->where('l.qty_balance >', 0)
            ->where('COALESCE(l.location_type, "") = ' . $this->db->escape((string)($group['location_type'] ?? '')), null, false)
            ->where('COALESCE(l.division_id, 0) = ' . (int)($group['division_id'] ?? 0), null, false)
            ->where('l.component_id', (int)($group['component_id'] ?? 0))
            ->where('l.uom_id', (int)($group['uom_id'] ?? 0))
            ->where("l.receipt_date >= {$monthKeySql} AND l.receipt_date <= {$todaySql}", null, false)
            ->order_by('l.receipt_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->limit(120)
            ->get();

        return $query ? $query->result_array() : [];
    }

    private function dashboard_adjustment_summary(): array
    {
        return [
            'daily' => $this->dashboard_adjustment_period_block('Hari Ini', date('Y-m-d'), date('Y-m-d')),
            'weekly' => $this->dashboard_adjustment_period_block('Minggu Ini', date('Y-m-d', strtotime('monday this week')), date('Y-m-d')),
            'monthly' => $this->dashboard_adjustment_period_block('Bulan Ini', date('Y-m-01'), date('Y-m-d')),
        ];
    }

    private function dashboard_adjustment_period_block(string $label, string $dateFrom, string $dateTo): array
    {
        return [
            'label' => $label,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'warehouse' => $this->dashboard_stock_adjustment_scope_summary('WAREHOUSE', $dateFrom, $dateTo),
            'division' => $this->dashboard_stock_adjustment_scope_summary('DIVISION', $dateFrom, $dateTo),
            'component' => $this->dashboard_component_adjustment_summary($dateFrom, $dateTo),
        ];
    }

    private function dashboard_stock_adjustment_scope_summary(string $scope, string $dateFrom, string $dateTo): array
    {
        if (
            !$this->dashboard_table_ready('inv_stock_adjustment')
            || !$this->dashboard_table_ready('inv_stock_adjustment_line')
        ) {
            return ['rows' => [], 'totals' => ['group_count' => 0, 'line_count' => 0, 'doc_count' => 0, 'value_out_total' => 0.0, 'value_plus_total' => 0.0, 'net_value_total' => 0.0]];
        }

        $scope = strtoupper(trim($scope));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $divisionNameColumn = $this->dashboard_division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $summaryRows = $this->db
            ->select('COALESCE(l.material_id, i.material_id, 0) AS material_id', false)
            ->select('COALESCE(l.item_id, 0) AS item_id', false)
            ->select('COALESCE(m.material_code, i.item_code, "-") AS object_code', false)
            ->select('COALESCE(m.material_name, i.item_name, "Tanpa Nama") AS object_name', false)
            ->select('h.division_id, COALESCE(h.destination_type, "OTHER") AS destination_type', false)
            ->select($divisionNameSelect, false)
            ->select('COUNT(DISTINCT h.id) AS doc_count', false)
            ->select('COUNT(*) AS line_count', false)
            ->select('SUM(COALESCE(l.qty_waste_content, 0)) AS qty_waste', false)
            ->select('SUM(COALESCE(l.qty_spoil_content, 0)) AS qty_spoil', false)
            ->select('SUM(COALESCE(l.qty_process_loss_content, 0)) AS qty_process_loss', false)
            ->select('SUM(COALESCE(l.qty_variance_content, 0)) AS qty_variance', false)
            ->select('SUM(COALESCE(l.qty_adjustment_plus_content, 0)) AS qty_plus', false)
            ->select('SUM(COALESCE(l.qty_waste_content, 0) * COALESCE(l.unit_cost, 0)) AS value_waste', false)
            ->select('SUM(COALESCE(l.qty_spoil_content, 0) * COALESCE(l.unit_cost, 0)) AS value_spoil', false)
            ->select('SUM(COALESCE(l.qty_process_loss_content, 0) * COALESCE(l.unit_cost, 0)) AS value_process_loss', false)
            ->select('SUM(COALESCE(l.qty_variance_content, 0) * COALESCE(l.unit_cost, 0)) AS value_variance', false)
            ->select('SUM(COALESCE(l.qty_adjustment_plus_content, 0) * COALESCE(l.unit_cost, 0)) AS value_plus', false)
            ->from('inv_stock_adjustment h')
            ->join('inv_stock_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = COALESCE(l.material_id, i.material_id)', 'left')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->where('h.stock_scope', $scope)
            ->where('h.status', 'POSTED')
            ->where('h.adjustment_date >=', $dateFrom)
            ->where('h.adjustment_date <=', $dateTo)
            ->group_by('COALESCE(l.material_id, i.material_id, 0)', false)
            ->group_by('COALESCE(l.item_id, 0)', false);

        if ($scope === 'DIVISION') {
            $summaryRows
                ->group_by('h.division_id')
                ->group_by('COALESCE(h.destination_type, "OTHER")', false);
        }

        $summaryRows = $summaryRows
            ->order_by('SUM(COALESCE(l.qty_waste_content, 0) * COALESCE(l.unit_cost, 0)) + SUM(COALESCE(l.qty_spoil_content, 0) * COALESCE(l.unit_cost, 0)) + SUM(COALESCE(l.qty_process_loss_content, 0) * COALESCE(l.unit_cost, 0)) + SUM(COALESCE(l.qty_variance_content, 0) * COALESCE(l.unit_cost, 0))', 'DESC', false)
            ->order_by('object_name', 'ASC')
            ->get()
            ->result_array();

        $displayRows = array_slice($summaryRows, 0, 50);

        $detailRows = $this->db
            ->select('COALESCE(l.material_id, i.material_id, 0) AS material_id', false)
            ->select('COALESCE(l.item_id, 0) AS item_id', false)
            ->select('COALESCE(m.material_code, i.item_code, "-") AS object_code', false)
            ->select('COALESCE(m.material_name, i.item_name, "Tanpa Nama") AS object_name', false)
            ->select('h.adjustment_no, h.adjustment_date, h.division_id, COALESCE(h.destination_type, "OTHER") AS destination_type, h.notes AS header_notes', false)
            ->select($divisionNameSelect, false)
            ->select('l.profile_name, l.profile_brand, l.profile_description, l.profile_content_uom_code, l.unit_cost, l.note', false)
            ->select('COALESCE(l.qty_waste_content, 0) AS qty_waste', false)
            ->select('COALESCE(l.qty_spoil_content, 0) AS qty_spoil', false)
            ->select('COALESCE(l.qty_process_loss_content, 0) AS qty_process_loss', false)
            ->select('COALESCE(l.qty_variance_content, 0) AS qty_variance', false)
            ->select('COALESCE(l.qty_adjustment_plus_content, 0) AS qty_plus', false)
            ->from('inv_stock_adjustment h')
            ->join('inv_stock_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = COALESCE(l.material_id, i.material_id)', 'left')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->where('h.stock_scope', $scope)
            ->where('h.status', 'POSTED')
            ->where('h.adjustment_date >=', $dateFrom)
            ->where('h.adjustment_date <=', $dateTo)
            ->order_by('h.adjustment_date', 'DESC')
            ->order_by('h.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

        $detailMap = [];
        $docNoSet = [];
        $visibleGroupKeys = [];
        foreach ($displayRows as $visibleRow) {
            $visibleGroupKeys[$this->dashboard_stock_adjustment_group_key(
                $scope,
                (int)($visibleRow['material_id'] ?? 0),
                (int)($visibleRow['item_id'] ?? 0),
                (int)($visibleRow['division_id'] ?? 0),
                (string)($visibleRow['destination_type'] ?? '')
            )] = true;
        }
        foreach ($detailRows as $detailRow) {
            $groupKey = $this->dashboard_stock_adjustment_group_key(
                $scope,
                (int)($detailRow['material_id'] ?? 0),
                (int)($detailRow['item_id'] ?? 0),
                (int)($detailRow['division_id'] ?? 0),
                (string)($detailRow['destination_type'] ?? '')
            );
            $adjustmentNo = (string)($detailRow['adjustment_no'] ?? '');
            if ($adjustmentNo !== '') {
                $docNoSet[$adjustmentNo] = true;
            }
            if (!isset($visibleGroupKeys[$groupKey])) {
                continue;
            }
            if (!isset($detailMap[$groupKey])) {
                $detailMap[$groupKey] = [];
            }
            $detailMap[$groupKey][] = [
                'adjustment_no' => $adjustmentNo,
                'adjustment_date' => (string)($detailRow['adjustment_date'] ?? ''),
                'profile_label' => trim(implode(' | ', array_filter([
                    (string)($detailRow['profile_name'] ?? ''),
                    (string)($detailRow['profile_brand'] ?? ''),
                    (string)($detailRow['profile_description'] ?? ''),
                ], static function ($value): bool {
                    return trim((string)$value) !== '';
                }))),
                'uom_code' => (string)($detailRow['profile_content_uom_code'] ?? ''),
                'qty_waste' => (float)($detailRow['qty_waste'] ?? 0),
                'qty_spoil' => (float)($detailRow['qty_spoil'] ?? 0),
                'qty_process_loss' => (float)($detailRow['qty_process_loss'] ?? 0),
                'qty_variance' => (float)($detailRow['qty_variance'] ?? 0),
                'qty_plus' => (float)($detailRow['qty_plus'] ?? 0),
                'unit_cost' => (float)($detailRow['unit_cost'] ?? 0),
                'note' => (string)($detailRow['note'] ?? ''),
                'header_notes' => (string)($detailRow['header_notes'] ?? ''),
            ];
        }

        $rows = [];
        $totals = ['group_count' => 0, 'line_count' => 0, 'doc_count' => 0, 'value_out_total' => 0.0, 'value_plus_total' => 0.0, 'net_value_total' => 0.0];
        foreach ($summaryRows as $row) {
            $valueOutTotal = round(
                (float)($row['value_waste'] ?? 0)
                + (float)($row['value_spoil'] ?? 0)
                + (float)($row['value_process_loss'] ?? 0)
                + (float)($row['value_variance'] ?? 0),
                2
            );
            $valuePlusTotal = round((float)($row['value_plus'] ?? 0), 2);
            $netValueTotal = round($valuePlusTotal - $valueOutTotal, 2);
            $totals['group_count']++;
            $totals['line_count'] += (int)($row['line_count'] ?? 0);
            $totals['value_out_total'] = round($totals['value_out_total'] + $valueOutTotal, 2);
            $totals['value_plus_total'] = round($totals['value_plus_total'] + $valuePlusTotal, 2);
            $totals['net_value_total'] = round($totals['net_value_total'] + $netValueTotal, 2);
        }
        foreach ($displayRows as $row) {
            $valueOutTotal = round(
                (float)($row['value_waste'] ?? 0)
                + (float)($row['value_spoil'] ?? 0)
                + (float)($row['value_process_loss'] ?? 0)
                + (float)($row['value_variance'] ?? 0),
                2
            );
            $valuePlusTotal = round((float)($row['value_plus'] ?? 0), 2);
            $netValueTotal = round($valuePlusTotal - $valueOutTotal, 2);
            $groupKey = $this->dashboard_stock_adjustment_group_key(
                $scope,
                (int)($row['material_id'] ?? 0),
                (int)($row['item_id'] ?? 0),
                (int)($row['division_id'] ?? 0),
                (string)($row['destination_type'] ?? '')
            );
            $locationName = $scope === 'WAREHOUSE'
                ? 'Gudang'
                : trim(implode(' · ', array_filter([
                    (string)($row['division_name'] ?? ''),
                    $this->dashboard_destination_label((string)($row['destination_type'] ?? '')),
                ])));

            $rows[] = [
                'group_key' => $groupKey,
                'object_code' => (string)($row['object_code'] ?? '-'),
                'object_name' => (string)($row['object_name'] ?? 'Tanpa Nama'),
                'location_name' => $locationName !== '' ? $locationName : ($scope === 'DIVISION' ? '-' : 'Gudang'),
                'doc_count' => (int)($row['doc_count'] ?? 0),
                'line_count' => (int)($row['line_count'] ?? 0),
                'qty_waste' => (float)($row['qty_waste'] ?? 0),
                'qty_spoil' => (float)($row['qty_spoil'] ?? 0),
                'qty_process_loss' => (float)($row['qty_process_loss'] ?? 0),
                'qty_variance' => (float)($row['qty_variance'] ?? 0),
                'qty_plus' => (float)($row['qty_plus'] ?? 0),
                'value_out_total' => $valueOutTotal,
                'value_plus_total' => $valuePlusTotal,
                'net_value_total' => $netValueTotal,
                'details' => $detailMap[$groupKey] ?? [],
            ];
        }
        $totals['doc_count'] = count($docNoSet);

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function dashboard_component_adjustment_summary(string $dateFrom, string $dateTo): array
    {
        if (
            !$this->dashboard_table_ready('inv_component_adjustment')
            || !$this->dashboard_table_ready('inv_component_adjustment_line')
        ) {
            return ['rows' => [], 'totals' => ['group_count' => 0, 'line_count' => 0, 'doc_count' => 0, 'value_out_total' => 0.0, 'value_plus_total' => 0.0, 'net_value_total' => 0.0]];
        }

        $divisionNameColumn = $this->dashboard_division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $summaryRows = $this->db
            ->select('l.component_id, COALESCE(c.component_code, "-") AS object_code, COALESCE(c.component_name, "Tanpa Nama") AS object_name', false)
            ->select('h.division_id, h.location_type', false)
            ->select($divisionNameSelect, false)
            ->select('COUNT(DISTINCT h.id) AS doc_count', false)
            ->select('COUNT(*) AS line_count', false)
            ->select('SUM(COALESCE(l.qty_waste, 0)) AS qty_waste', false)
            ->select('SUM(COALESCE(l.qty_spoil, 0)) AS qty_spoil', false)
            ->select('SUM(COALESCE(l.qty_adjust_neg, 0)) AS qty_minus', false)
            ->select('SUM(COALESCE(l.qty_adjust_pos, 0)) AS qty_plus', false)
            ->select('SUM(COALESCE(l.qty_waste, 0) * COALESCE(l.unit_cost, 0)) AS value_waste', false)
            ->select('SUM(COALESCE(l.qty_spoil, 0) * COALESCE(l.unit_cost, 0)) AS value_spoil', false)
            ->select('SUM(COALESCE(l.qty_adjust_neg, 0) * COALESCE(l.unit_cost, 0)) AS value_minus', false)
            ->select('SUM(COALESCE(l.qty_adjust_pos, 0) * COALESCE(l.unit_cost, 0)) AS value_plus', false)
            ->from('inv_component_adjustment h')
            ->join('inv_component_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->where('h.status', 'POSTED')
            ->where('h.adjustment_date >=', $dateFrom)
            ->where('h.adjustment_date <=', $dateTo)
            ->group_by('l.component_id')
            ->group_by('h.division_id')
            ->group_by('h.location_type')
            ->order_by('SUM(COALESCE(l.qty_waste, 0) * COALESCE(l.unit_cost, 0)) + SUM(COALESCE(l.qty_spoil, 0) * COALESCE(l.unit_cost, 0)) + SUM(COALESCE(l.qty_adjust_neg, 0) * COALESCE(l.unit_cost, 0))', 'DESC', false)
            ->order_by('object_name', 'ASC')
            ->get()
            ->result_array();

        $displayRows = array_slice($summaryRows, 0, 50);

        $detailRows = $this->db
            ->select('l.component_id, COALESCE(c.component_code, "-") AS object_code, COALESCE(c.component_name, "Tanpa Nama") AS object_name', false)
            ->select('h.adjustment_no, h.adjustment_date, h.division_id, h.location_type, h.notes AS header_notes', false)
            ->select($divisionNameSelect, false)
            ->select('u.code AS uom_code, l.unit_cost, l.note', false)
            ->select('COALESCE(l.qty_waste, 0) AS qty_waste', false)
            ->select('COALESCE(l.qty_spoil, 0) AS qty_spoil', false)
            ->select('COALESCE(l.qty_adjust_neg, 0) AS qty_minus', false)
            ->select('COALESCE(l.qty_adjust_pos, 0) AS qty_plus', false)
            ->from('inv_component_adjustment h')
            ->join('inv_component_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->where('h.status', 'POSTED')
            ->where('h.adjustment_date >=', $dateFrom)
            ->where('h.adjustment_date <=', $dateTo)
            ->order_by('h.adjustment_date', 'DESC')
            ->order_by('h.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

        $detailMap = [];
        $docNoSet = [];
        $visibleGroupKeys = [];
        foreach ($displayRows as $visibleRow) {
            $visibleGroupKeys[$this->dashboard_component_adjustment_group_key(
                (int)($visibleRow['component_id'] ?? 0),
                (int)($visibleRow['division_id'] ?? 0),
                (string)($visibleRow['location_type'] ?? '')
            )] = true;
        }
        foreach ($detailRows as $detailRow) {
            $groupKey = $this->dashboard_component_adjustment_group_key(
                (int)($detailRow['component_id'] ?? 0),
                (int)($detailRow['division_id'] ?? 0),
                (string)($detailRow['location_type'] ?? '')
            );
            $adjustmentNo = (string)($detailRow['adjustment_no'] ?? '');
            if ($adjustmentNo !== '') {
                $docNoSet[$adjustmentNo] = true;
            }
            if (!isset($visibleGroupKeys[$groupKey])) {
                continue;
            }
            if (!isset($detailMap[$groupKey])) {
                $detailMap[$groupKey] = [];
            }
            $detailMap[$groupKey][] = [
                'adjustment_no' => $adjustmentNo,
                'adjustment_date' => (string)($detailRow['adjustment_date'] ?? ''),
                'uom_code' => (string)($detailRow['uom_code'] ?? ''),
                'qty_waste' => (float)($detailRow['qty_waste'] ?? 0),
                'qty_spoil' => (float)($detailRow['qty_spoil'] ?? 0),
                'qty_minus' => (float)($detailRow['qty_minus'] ?? 0),
                'qty_plus' => (float)($detailRow['qty_plus'] ?? 0),
                'unit_cost' => (float)($detailRow['unit_cost'] ?? 0),
                'note' => (string)($detailRow['note'] ?? ''),
                'header_notes' => (string)($detailRow['header_notes'] ?? ''),
            ];
        }

        $rows = [];
        $totals = ['group_count' => 0, 'line_count' => 0, 'doc_count' => 0, 'value_out_total' => 0.0, 'value_plus_total' => 0.0, 'net_value_total' => 0.0];
        foreach ($summaryRows as $row) {
            $valueOutTotal = round(
                (float)($row['value_waste'] ?? 0)
                + (float)($row['value_spoil'] ?? 0)
                + (float)($row['value_minus'] ?? 0),
                2
            );
            $valuePlusTotal = round((float)($row['value_plus'] ?? 0), 2);
            $netValueTotal = round($valuePlusTotal - $valueOutTotal, 2);
            $totals['group_count']++;
            $totals['line_count'] += (int)($row['line_count'] ?? 0);
            $totals['value_out_total'] = round($totals['value_out_total'] + $valueOutTotal, 2);
            $totals['value_plus_total'] = round($totals['value_plus_total'] + $valuePlusTotal, 2);
            $totals['net_value_total'] = round($totals['net_value_total'] + $netValueTotal, 2);
        }
        foreach ($displayRows as $row) {
            $valueOutTotal = round(
                (float)($row['value_waste'] ?? 0)
                + (float)($row['value_spoil'] ?? 0)
                + (float)($row['value_minus'] ?? 0),
                2
            );
            $valuePlusTotal = round((float)($row['value_plus'] ?? 0), 2);
            $netValueTotal = round($valuePlusTotal - $valueOutTotal, 2);
            $groupKey = $this->dashboard_component_adjustment_group_key(
                (int)($row['component_id'] ?? 0),
                (int)($row['division_id'] ?? 0),
                (string)($row['location_type'] ?? '')
            );
            $locationName = trim(implode(' · ', array_filter([
                (string)($row['division_name'] ?? ''),
                (string)($row['location_type'] ?? ''),
            ])));

            $rows[] = [
                'group_key' => $groupKey,
                'object_code' => (string)($row['object_code'] ?? '-'),
                'object_name' => (string)($row['object_name'] ?? 'Tanpa Nama'),
                'location_name' => $locationName !== '' ? $locationName : '-',
                'doc_count' => (int)($row['doc_count'] ?? 0),
                'line_count' => (int)($row['line_count'] ?? 0),
                'qty_waste' => (float)($row['qty_waste'] ?? 0),
                'qty_spoil' => (float)($row['qty_spoil'] ?? 0),
                'qty_minus' => (float)($row['qty_minus'] ?? 0),
                'qty_plus' => (float)($row['qty_plus'] ?? 0),
                'value_out_total' => $valueOutTotal,
                'value_plus_total' => $valuePlusTotal,
                'net_value_total' => $netValueTotal,
                'details' => $detailMap[$groupKey] ?? [],
            ];
        }
        $totals['doc_count'] = count($docNoSet);

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function dashboard_top_selling_products(): array
    {
        return [
            'daily' => [
                'label' => 'Hari Ini',
                'groups' => $this->dashboard_top_selling_products_window(date('Y-m-d'), date('Y-m-d')),
            ],
            'weekly' => [
                'label' => 'Minggu Ini',
                'groups' => $this->dashboard_top_selling_products_window(date('Y-m-d', strtotime('monday this week')), date('Y-m-d')),
            ],
            'monthly' => [
                'label' => 'Bulan Ini',
                'groups' => $this->dashboard_top_selling_products_window(date('Y-m-01'), date('Y-m-d')),
            ],
        ];
    }

    private function dashboard_top_selling_products_window(string $dateFrom, string $dateTo): array
    {
        if (!$this->dashboard_table_ready('pos_order') || !$this->dashboard_table_ready('pos_order_line')) {
            return [];
        }

        $eventDateExpr = $this->dashboard_pos_event_date_expr('o');
        $rows = $this->db
            ->select('CASE WHEN l.line_type = "BUNDLE_HEADER" AND l.bundle_id IS NOT NULL THEN CONCAT("BND-", l.bundle_id) ELSE CONCAT("PRD-", l.product_id) END AS row_key', false)
            ->select('COALESCE(b.bundle_name, p.product_name, "-") AS product_name', false)
            ->select('COALESCE(b.bundle_code, p.product_code, "-") AS product_code', false)
            ->select('COALESCE(pd_bundle.name, pd_product.name, "-") AS division_name', false)
            ->select('SUM(COALESCE(l.qty, 0)) AS qty_total', false)
            ->select('SUM(COALESCE(l.net_amount, 0)) AS net_total', false)
            ->select('COUNT(DISTINCT o.id) AS order_count', false)
            ->from('pos_order_line l')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->join('mst_product_division pd_product', 'pd_product.id = pc.product_division_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd_bundle', 'pd_bundle.id = b.product_division_id', 'left')
            ->where_not_in('o.status', $this->dashboard_pos_excluded_statuses())
            ->where_not_in('l.line_status', ['VOID', 'REFUNDED_FULL'])
            ->where_in('l.line_type', ['PRODUCT', 'BUNDLE_HEADER'])
            ->where('COALESCE(pd_bundle.name, pd_product.name) IN ("FOOD","BEVERAGE")', null, false)
            ->where($eventDateExpr . " >= '" . $dateFrom . "'", null, false)
            ->where($eventDateExpr . " <= '" . $dateTo . "'", null, false)
            ->group_by('row_key', false)
            ->group_by('COALESCE(pd_bundle.name, pd_product.name)', false)
            ->order_by('qty_total', 'DESC', false)
            ->order_by('net_total', 'DESC', false)
            ->get()
            ->result_array();

        $grouped = [
            'FOOD' => ['label' => 'Food', 'rows' => []],
            'BEVERAGE' => ['label' => 'Beverage', 'rows' => []],
        ];
        foreach ($rows as $row) {
            $divisionName = strtoupper(trim((string)($row['division_name'] ?? '')));
            if (!isset($grouped[$divisionName])) {
                continue;
            }
            $grouped[$divisionName]['rows'][] = [
                'product_name' => (string)($row['product_name'] ?? '-'),
                'product_code' => (string)($row['product_code'] ?? '-'),
                'division_name' => (string)($row['division_name'] ?? '-'),
                'qty_total' => (float)($row['qty_total'] ?? 0),
                'net_total' => (float)($row['net_total'] ?? 0),
                'order_count' => (int)($row['order_count'] ?? 0),
            ];
        }
        return $grouped;
    }

    private function dashboard_stock_adjustment_group_key(string $scope, int $materialId, int $itemId, int $divisionId, string $destinationType): string
    {
        return implode('|', [
            strtoupper($scope),
            $materialId,
            $itemId,
            $scope === 'DIVISION' ? $divisionId : 0,
            $scope === 'DIVISION' ? strtoupper(trim($destinationType)) : 'GUDANG',
        ]);
    }

    private function dashboard_component_adjustment_group_key(int $componentId, int $divisionId, string $locationType): string
    {
        return implode('|', [
            $componentId,
            $divisionId,
            strtoupper(trim($locationType)),
        ]);
    }

    private function dashboard_destination_label(string $destinationType): string
    {
        $map = [
            'GUDANG' => 'Gudang',
            'BAR' => 'Bar Reguler',
            'KITCHEN' => 'Kitchen Reguler',
            'BAR_EVENT' => 'Bar Event',
            'KITCHEN_EVENT' => 'Kitchen Event',
            'OFFICE' => 'Office',
            'OTHER' => 'Reguler',
        ];
        $key = strtoupper(trim($destinationType));
        return $map[$key] ?? ($key !== '' ? $key : 'Reguler');
    }

    private function require_registered_page_permission(string $pageCode): void
    {
        if ($this->is_registered_page($pageCode)) {
            $this->require_permission($pageCode, 'view');
        }
    }

    private function is_registered_page(string $pageCode): bool
    {
        if (!array_key_exists($pageCode, $this->registeredPageCache)) {
            $exists = $this->db
                ->select('id')
                ->from('sys_page')
                ->where('page_code', $pageCode)
                ->where('is_active', 1)
                ->limit(1)
                ->get()
                ->row_array();

            $this->registeredPageCache[$pageCode] = !empty($exists);
        }

        return $this->registeredPageCache[$pageCode];
    }

    private function dashboard_filters(): array
    {
        $today = date('Y-m-d');
        $period = strtolower(trim((string)$this->input->get('period', true)));
        if (!in_array($period, ['today', 'month', '7', '30', 'custom'], true)) {
            $period = 'today';
        }

        $dateFrom = $today;
        $dateTo = $today;
        $periodLabel = 'Hari Ini';

        if ($period === 'month') {
            $dateFrom = date('Y-m-01');
            $dateTo = $today;
            $periodLabel = 'Bulan Ini';
        } elseif ($period === '7') {
            $dateFrom = date('Y-m-d', strtotime('-6 days', strtotime($today)));
            $periodLabel = '7 Hari Terakhir';
        } elseif ($period === '30') {
            $dateFrom = date('Y-m-d', strtotime('-29 days', strtotime($today)));
            $periodLabel = '30 Hari Terakhir';
        } elseif ($period === 'custom') {
            $rawFrom = trim((string)$this->input->get('date_from', true));
            $rawTo = trim((string)$this->input->get('date_to', true));
            if ($this->is_valid_date($rawFrom) && $this->is_valid_date($rawTo)) {
                $dateFrom = $rawFrom;
                $dateTo = $rawTo;
                if ($dateFrom > $dateTo) {
                    $tmp = $dateFrom;
                    $dateFrom = $dateTo;
                    $dateTo = $tmp;
                }
                $periodLabel = date('d-m-Y', strtotime($dateFrom)) . ' s/d ' . date('d-m-Y', strtotime($dateTo));
            } else {
                $period = 'today';
            }
        }

        return [
            'period' => $period,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_label' => $periodLabel,
        ];
    }

    private function is_valid_date(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }

    private function dashboard_sales_statuses(): array
    {
        return ['PAID_PARTIAL', 'PAID', 'IN_KITCHEN', 'READY', 'SERVED'];
    }

    private function dashboard_pos_excluded_statuses(): array
    {
        return ['DRAFT', 'PENDING', 'VOID'];
    }

    private function dashboard_pending_sr_statuses(): array
    {
        return ['DRAFT', 'SUBMITTED', 'APPROVED', 'PARTIAL_FULFILLED'];
    }

    private function dashboard_active_po_statuses(): array
    {
        return ['DRAFT', 'APPROVED', 'ORDERED', 'PARTIAL_RECEIVED'];
    }

    private function dashboard_kpi(array $filters, array $stockCards, array $salesOverview): array
    {
        $kpi = [
            'transaction_count' => 0,
            'sales_total' => 0.0,
            'gross_sales_total' => 0.0,
            'refund_total' => 0.0,
            'critical_stock_total' => 0,
            'present_employee_count' => 0,
            'total_nilai_po' => 0.0,
            'total_nilai_sr' => 0.0,
        ];

        $kpi['transaction_count'] = (int)($salesOverview['transaction_count'] ?? 0);
        $kpi['gross_sales_total'] = (float)($salesOverview['gross_sales_total'] ?? 0);
        $kpi['refund_total'] = (float)($salesOverview['refund_total'] ?? 0);
        $kpi['sales_total'] = (float)($salesOverview['net_sales_total'] ?? 0);

        if ($this->dashboard_table_ready('att_daily')) {
            $kpi['present_employee_count'] = (int)$this->db
                ->select('COUNT(DISTINCT employee_id) AS total', false)
                ->from('att_daily')
                ->where('attendance_date >=', $filters['date_from'])
                ->where('attendance_date <=', $filters['date_to'])
                ->where_in('attendance_status', ['PRESENT', 'LATE', 'HOLIDAY'])
                ->get()->row('total');
        }

        if ($this->dashboard_table_ready('pur_purchase_order')) {
            $kpi['total_nilai_po'] = (float)$this->db
                ->select('COALESCE(SUM(grand_total), 0) AS total', false)
                ->from('pur_purchase_order')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where('status !=', 'VOID')
                ->get()->row('total');
        }

        if ($this->dashboard_table_ready('pur_store_request') && $this->dashboard_table_ready('pur_store_request_line')) {
            $kpi['total_nilai_sr'] = $this->dashboard_total_nilai_sr($filters);
        }
        foreach ($stockCards as $stockCard) {
            $kpi['critical_stock_total'] += (int)($stockCard['critical_count'] ?? 0);
        }

        return $kpi;
    }

    private function dashboard_trend_series(array $filters): array
    {
        $labels = [];
        $sales = [];
        $refunds = [];
        $orders = [];
        $poMap = [];
        $srMap = [];
        $statusMap = [];

        if ($this->dashboard_table_ready('pos_order')) {
            $eventDateExpr = $this->dashboard_pos_event_date_expr('o');
            $this->db
                ->select($eventDateExpr . ' AS day_key', false)
                ->select('COUNT(DISTINCT o.id) AS order_count', false)
                ->select('COALESCE(SUM(o.paid_total),0) AS gross_sales_total', false)
                ->from('pos_order o')
                ->where_not_in('o.status', $this->dashboard_pos_excluded_statuses())
                ->where($eventDateExpr . " >= '" . $filters['date_from'] . "'", null, false)
                ->where($eventDateExpr . " <= '" . $filters['date_to'] . "'", null, false);

            if ($this->dashboard_pos_refund_available()) {
                $this->db
                    ->select('COALESCE(SUM(COALESCE(rf.refund_amount,0)),0) AS refund_total', false)
                    ->join($this->dashboard_pos_refund_subquery() . ' rf', 'rf.order_id = o.id', 'left', false);
            } else {
                $this->db->select('0 AS refund_total', false);
            }

            $rows = $this->db
                ->group_by($eventDateExpr, false)
                ->order_by('day_key', 'ASC')
                ->get()->result_array();
            foreach ($rows as $row) {
                $grossSalesTotal = (float)($row['gross_sales_total'] ?? 0);
                $refundTotal = (float)($row['refund_total'] ?? 0);
                $statusMap[(string)($row['day_key'] ?? '')] = [
                    'orders' => (int)($row['order_count'] ?? 0),
                    'sales' => max(0, $grossSalesTotal - $refundTotal),
                    'refunds' => $refundTotal,
                ];
            }
        }

        if ($this->dashboard_table_ready('pur_purchase_order')) {
            $poRows = $this->db
                ->select('request_date AS day_key, COALESCE(SUM(grand_total), 0) AS po_total', false)
                ->from('pur_purchase_order')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where('status !=', 'VOID')
                ->group_by('request_date')
                ->get()->result_array();
            foreach ($poRows as $row) {
                $poMap[(string)($row['day_key'] ?? '')] = (float)($row['po_total'] ?? 0);
            }
        }

        if ($this->dashboard_table_ready('pur_store_request')) {
            $srRows = $this->db
                ->select('request_date AS day_key, COUNT(*) AS sr_count', false)
                ->from('pur_store_request')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where('status !=', 'VOID')
                ->group_by('request_date')
                ->get()->result_array();
            foreach ($srRows as $row) {
                $srMap[(string)($row['day_key'] ?? '')] = (int)($row['sr_count'] ?? 0);
            }
        }

        $period = new DatePeriod(
            new DateTime($filters['date_from']),
            new DateInterval('P1D'),
            (new DateTime($filters['date_to']))->modify('+1 day')
        );
        $po = [];
        $sr = [];
        foreach ($period as $dateObj) {
            $key = $dateObj->format('Y-m-d');
            $labels[] = $dateObj->format('d M');
            $sales[] = (float)($statusMap[$key]['sales'] ?? 0);
            $refunds[] = (float)($statusMap[$key]['refunds'] ?? 0);
            $orders[] = (int)($statusMap[$key]['orders'] ?? 0);
            $po[] = (float)($poMap[$key] ?? 0);
            $sr[] = (int)($srMap[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'refunds' => $refunds,
            'orders' => $orders,
            'po' => $po,
            'sr' => $sr,
        ];
    }

    private function dashboard_pos_status_rows(array $filters): array
    {
        if (!$this->dashboard_table_ready('pos_order')) {
            return [];
        }

        $eventDateExpr = $this->dashboard_pos_event_date_expr();

        return $this->db
            ->select('status, COUNT(*) AS total', false)
            ->from('pos_order')
            ->where_not_in('status', $this->dashboard_pos_excluded_statuses())
            ->where($eventDateExpr . " >= '" . $filters['date_from'] . "'", null, false)
            ->where($eventDateExpr . " <= '" . $filters['date_to'] . "'", null, false)
            ->group_by('status')
            ->order_by('total', 'DESC')
            ->get()->result_array();
    }

    private function dashboard_pos_sales_overview(array $filters, ?string $scope = null): array
    {
        $overview = [
            'transaction_count' => 0,
            'gross_sales_total' => 0.0,
            'refund_total' => 0.0,
            'net_sales_total' => 0.0,
        ];
        if (!$this->dashboard_table_ready('pos_order')) {
            return $overview;
        }

        $eventDateExpr = $this->dashboard_pos_event_date_expr('o');
        $this->db
            ->select('COUNT(DISTINCT o.id) AS transaction_count', false)
            ->select('COALESCE(SUM(o.paid_total),0) AS gross_sales_total', false)
            ->from('pos_order o')
            ->where_not_in('o.status', $this->dashboard_pos_excluded_statuses())
            ->where($eventDateExpr . " >= '" . $filters['date_from'] . "'", null, false)
            ->where($eventDateExpr . " <= '" . $filters['date_to'] . "'", null, false);

        if ($scope !== null && $scope !== '') {
            $this->db->where('o.order_scope', strtoupper($scope));
        }

        if ($this->dashboard_pos_refund_available()) {
            $this->db
                ->select('COALESCE(SUM(COALESCE(rf.refund_amount,0)),0) AS refund_total', false)
                ->join($this->dashboard_pos_refund_subquery() . ' rf', 'rf.order_id = o.id', 'left', false);
        } else {
            $this->db->select('0 AS refund_total', false);
        }

        $row = $this->db->get()->row_array() ?: [];
        $overview['transaction_count'] = (int)($row['transaction_count'] ?? 0);
        $overview['gross_sales_total'] = (float)($row['gross_sales_total'] ?? 0);
        $overview['refund_total'] = (float)($row['refund_total'] ?? 0);
        $overview['net_sales_total'] = max(0, $overview['gross_sales_total'] - $overview['refund_total']);

        return $overview;
    }

    private function dashboard_pos_scope_rows(array $filters): array
    {
        $scopes = ['REGULAR', 'EVENT'];
        $labels = [
            'REGULAR' => 'Reguler',
            'EVENT' => 'Event',
        ];
        $rows = [];

        foreach ($scopes as $scope) {
            $overview = $this->dashboard_pos_sales_overview($filters, $scope);
            $rows[] = [
                'scope_code' => $scope,
                'scope_label' => $labels[$scope] ?? $scope,
                'transaction_count' => (int)($overview['transaction_count'] ?? 0),
                'gross_sales_total' => (float)($overview['gross_sales_total'] ?? 0),
                'refund_total' => (float)($overview['refund_total'] ?? 0),
                'net_sales_total' => (float)($overview['net_sales_total'] ?? 0),
            ];
        }

        return $rows;
    }

    private function dashboard_pos_event_date_expr(string $alias = 'pos_order'): string
    {
        return 'DATE(COALESCE(' . $alias . '.paid_at, ' . $alias . '.confirmed_at, ' . $alias . '.ordered_at, ' . $alias . '.created_at))';
    }

    private function dashboard_pos_refund_available(): bool
    {
        return $this->dashboard_table_ready('pos_refund_line') && $this->dashboard_table_ready('pos_order_line');
    }

    private function dashboard_pos_refund_subquery(): string
    {
        return "(
            SELECT l.order_id,
                   COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount
            FROM pos_refund_line rl
            INNER JOIN pos_order_line l ON l.id = rl.order_line_id
            GROUP BY l.order_id
        )";
    }

    private function dashboard_purchase_status_rows(array $filters): array
    {
        if (!$this->dashboard_table_ready('pur_purchase_order')) {
            return [];
        }

        return $this->db
            ->select('status, COUNT(*) AS total, COALESCE(SUM(grand_total),0) AS grand_total', false)
            ->from('pur_purchase_order')
            ->group_by('status')
            ->order_by('total', 'DESC')
            ->get()->result_array();
    }

    private function dashboard_store_request_status_rows(array $filters): array
    {
        if (!$this->dashboard_table_ready('pur_store_request')) {
            return [];
        }

        return $this->db
            ->select('status, COUNT(*) AS total', false)
            ->from('pur_store_request')
            ->group_by('status')
            ->order_by('total', 'DESC')
            ->get()->result_array();
    }

    private function dashboard_total_nilai_sr(array $filters): float
    {
        if (!$this->dashboard_table_ready('pur_store_request') || !$this->dashboard_table_ready('pur_store_request_line')) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(srl.qty_buy_requested * COALESCE(pc.last_unit_price, 0)), 0) AS total
                FROM pur_store_request sr
                INNER JOIN pur_store_request_line srl ON srl.store_request_id = sr.id
                LEFT JOIN (
                    SELECT profile_key, MAX(last_unit_price) AS last_unit_price
                    FROM mst_purchase_catalog
                    WHERE is_active = 1
                    GROUP BY profile_key
                ) pc ON pc.profile_key = srl.profile_key
                WHERE sr.request_date >= " . $this->db->escape($filters['date_from']) . "
                  AND sr.request_date <= " . $this->db->escape($filters['date_to']) . "
                  AND sr.status != 'VOID'";

        $result = $this->dashboard_safe_query($sql);
        return $result ? (float)$result->row('total') : 0.0;
    }

    private function dashboard_reconcile_mismatch_summary(): array
    {
        $asOfDate = date('Y-m-d');
        $monthStart = date('Y-m-01', strtotime($asOfDate));
        $summary = [
            'as_of_date' => $asOfDate,
            'material' => [
                'total' => 0,
                'locations' => [],
                'rows' => [],
                'url' => site_url('inventory/stock/division/reconcile') . '?' . http_build_query([
                    'as_of_date' => $asOfDate,
                    'per_page' => 25,
                ]),
            ],
            'component' => [
                'total' => 0,
                'qty_mismatch_count' => 0,
                'value_mismatch_count' => 0,
                'locations' => [],
                'rows' => [],
                'url' => site_url('production/component-reconcile') . '?' . http_build_query([
                    'as_of_date' => $asOfDate,
                    'date_from' => $monthStart,
                    'date_to' => $asOfDate,
                    'per_page' => 25,
                ]),
            ],
        ];

        try {
            $summary['material'] = $this->dashboard_material_reconcile_mismatch($asOfDate, $summary['material']);
        } catch (Throwable $e) {
            log_message('error', 'Dashboard material reconcile mismatch failed: ' . $e->getMessage());
            $summary['material']['error'] = 'Ringkasan bahan baku belum bisa dimuat.';
        }

        try {
            $summary['component'] = $this->dashboard_component_reconcile_mismatch($asOfDate, $monthStart, $summary['component']);
        } catch (Throwable $e) {
            log_message('error', 'Dashboard component reconcile mismatch failed: ' . $e->getMessage());
            $summary['component']['error'] = 'Ringkasan component belum bisa dimuat.';
        }

        return $summary;
    }

    private function dashboard_material_reconcile_mismatch(string $asOfDate, array $bucket): array
    {
        if (!$this->dashboard_table_ready('inv_division_monthly_stock')) {
            return $bucket;
        }

        $this->load->model('Purchase_model');
        $compare = $this->Purchase_model->list_division_material_stock_compare($asOfDate, '', null, 2000, 'ALL', true);
        $rows = is_array($compare['rows'] ?? null) ? $compare['rows'] : [];
        $locations = [];
        $topRows = [];

        foreach ($rows as $row) {
            if (!empty($row['is_match'])) {
                continue;
            }

            $divisionId = (int)($row['division_id'] ?? 0);
            $destination = strtoupper(trim((string)($row['destination_type'] ?? $row['destination_group'] ?? 'ALL')));
            if ($destination === '') {
                $destination = 'ALL';
            }

            $divisionName = trim((string)($row['division_name'] ?? $row['division_code'] ?? ('Divisi #' . $divisionId)));
            $locationName = trim((string)($row['destination_name'] ?? $destination));
            $locationLabel = $this->dashboard_reconcile_location_label($divisionName, $locationName);
            $locationKey = $divisionId . '|' . $destination;
            $locationUrl = site_url('inventory/stock/division/reconcile') . '?' . http_build_query([
                'as_of_date' => $asOfDate,
                'division_id' => $divisionId,
                'destination' => $destination,
                'per_page' => 25,
            ]);

            if (!isset($locations[$locationKey])) {
                $locations[$locationKey] = [
                    'label' => $locationLabel,
                    'division_id' => $divisionId,
                    'location' => $destination,
                    'total' => 0,
                    'url' => $locationUrl,
                ];
            }
            $locations[$locationKey]['total']++;

            $gap = $this->dashboard_material_reconcile_gap($row);
            $valueGap = round((float)($row['lot_vs_balance_value_delta'] ?? 0), 2);
            $isValueOnly = abs($gap) <= 0.01 && abs($valueGap) > 1.0;
            $topRows[] = [
                'name' => (string)($row['material_name'] ?? 'Bahan baku'),
                'code' => (string)($row['material_code'] ?? ''),
                'location' => $locationLabel,
                'gap' => $gap,
                'gap_value' => $valueGap,
                'gap_type' => $isValueOnly ? 'currency' : 'qty',
                'sort_gap' => $isValueOnly ? abs($valueGap) : abs($gap),
                'reason' => $isValueOnly
                    ? 'Qty sama, nilai FIFO berbeda'
                    : trim((string)($row['suspect_reason'] ?? '')),
                'url' => site_url('inventory/stock/division/reconcile') . '?' . http_build_query([
                    'as_of_date' => $asOfDate,
                    'division_id' => $divisionId,
                    'destination' => $destination,
                    'q' => (string)($row['material_name'] ?? ''),
                    'per_page' => 25,
                ]),
            ];
        }

        uasort($locations, static function ($a, $b) {
            return ((int)$b['total'] <=> (int)$a['total']) ?: strcmp((string)$a['label'], (string)$b['label']);
        });
        usort($topRows, static fn($a, $b) => (float)($b['sort_gap'] ?? 0) <=> (float)($a['sort_gap'] ?? 0));

        $bucket['total'] = count($topRows);
        $bucket['locations'] = array_slice(array_values($locations), 0, 6);
        $bucket['rows'] = array_slice($topRows, 0, 6);
        return $bucket;
    }

    private function dashboard_component_reconcile_mismatch(string $asOfDate, string $monthStart, array $bucket): array
    {
        if (!$this->dashboard_table_ready('inv_component_monthly_stock')) {
            return $bucket;
        }

        $this->load->model('Production_model');
        $compare = $this->Production_model->component_reconcile_rows([
            'as_of_date' => $asOfDate,
            'date_from' => $monthStart,
            'date_to' => $asOfDate,
            'location_type' => '',
            'division_id' => null,
            'type' => '',
            'q' => '',
        ], 2000);
        $rows = is_array($compare['rows'] ?? null) ? $compare['rows'] : [];
        $locations = [];
        $topRows = [];
        $totalMismatchRows = 0;
        $qtyMismatchCount = 0;
        $valueMismatchCount = 0;

        foreach ($rows as $row) {
            // Verdict dan nominal berasal dari Production_model agar dashboard dan
            // halaman reconcile memakai toleransi FIFO yang sama.
            if (!empty($row['is_match'])) {
                continue;
            }

            $qtyIsMatch = !empty($row['qty_is_match']);
            $hasValueMismatch = !empty($row['has_lot_value_mismatch']);
            $valueGap = (float)($row['monthly_lot_value_gap'] ?? 0);
            $isValueOnly = $qtyIsMatch && $hasValueMismatch;
            $gap = $this->dashboard_component_reconcile_gap($row);

            $totalMismatchRows++;
            if (!$qtyIsMatch) {
                $qtyMismatchCount++;
            }
            if ($hasValueMismatch) {
                $valueMismatchCount++;
            }

            $divisionId = (int)($row['division_id'] ?? 0);
            $locationType = strtoupper(trim((string)($row['location_type'] ?? 'REGULER')));
            if ($locationType === '') {
                $locationType = 'REGULER';
            }

            $divisionName = trim((string)($row['division_name'] ?? ('Divisi #' . $divisionId)));
            $locationLabel = $this->dashboard_reconcile_location_label($divisionName, $locationType);
            $locationKey = $divisionId . '|' . $locationType;
            $locationUrl = site_url('production/component-reconcile') . '?' . http_build_query([
                'as_of_date' => $asOfDate,
                'date_from' => $monthStart,
                'date_to' => $asOfDate,
                'division_id' => $divisionId,
                'location_type' => $locationType,
                'per_page' => 25,
            ]);

            if (!isset($locations[$locationKey])) {
                $locations[$locationKey] = [
                    'label' => $locationLabel,
                    'division_id' => $divisionId,
                    'location' => $locationType,
                    'total' => 0,
                    'url' => $locationUrl,
                ];
            }
            $locations[$locationKey]['total']++;

            $topRows[] = [
                'name' => (string)($row['component_name'] ?? 'Component'),
                'code' => (string)($row['component_code'] ?? ''),
                'location' => $locationLabel,
                'gap' => $gap,
                'gap_value' => $valueGap,
                'gap_type' => $isValueOnly ? 'currency' : 'qty',
                'sort_gap' => $isValueOnly ? abs($valueGap) : abs($gap),
                'reason' => $isValueOnly
                    ? 'Qty sama, nilai FIFO berbeda'
                    : trim((string)($row['suspect_reason'] ?? '')),
                'url' => site_url('production/component-reconcile') . '?' . http_build_query([
                    'as_of_date' => $asOfDate,
                    'date_from' => $monthStart,
                    'date_to' => $asOfDate,
                    'division_id' => $divisionId,
                    'location_type' => $locationType,
                    'q' => (string)($row['component_name'] ?? ''),
                    'per_page' => 25,
                ]),
            ];
        }

        uasort($locations, static function ($a, $b) {
            return ((int)$b['total'] <=> (int)$a['total']) ?: strcmp((string)$a['label'], (string)$b['label']);
        });
        usort($topRows, static fn($a, $b) => (float)($b['sort_gap'] ?? 0) <=> (float)($a['sort_gap'] ?? 0));

        $bucket['total'] = $totalMismatchRows;
        $bucket['qty_mismatch_count'] = $qtyMismatchCount;
        $bucket['value_mismatch_count'] = $valueMismatchCount;
        $bucket['locations'] = array_slice(array_values($locations), 0, 6);
        $bucket['rows'] = array_slice($topRows, 0, 6);
        return $bucket;
    }

    private function dashboard_reconcile_location_label(string $divisionName, string $locationName): string
    {
        $divisionName = trim($divisionName) !== '' ? trim($divisionName) : 'Tanpa Divisi';
        $locationName = trim($locationName) !== '' ? trim($locationName) : 'Reguler';
        if (strcasecmp($divisionName, $locationName) === 0) {
            return $divisionName;
        }
        return $divisionName . ' - ' . $locationName;
    }

    private function dashboard_material_reconcile_gap(array $row): float
    {
        $profileLotDelta = (float)($row['profile_lot_delta_content'] ?? 0);
        foreach ((array)($row['lot_profile_breakdown'] ?? []) as $profile) {
            $candidate = (float)($profile['delta'] ?? 0);
            if (abs($candidate) > abs($profileLotDelta)) {
                $profileLotDelta = $candidate;
            }
        }

        $candidates = [
            (float)($row['delta_balance_vs_movement'] ?? 0),
            (float)($row['delta_daily_vs_movement'] ?? 0),
            (float)($row['delta_matrix_vs_movement'] ?? 0),
            (float)($row['daily_log_gap_content'] ?? 0),
            $profileLotDelta,
            (float)($row['lot_vs_balance_delta'] ?? $row['lot_delta_content'] ?? 0),
        ];
        usort($candidates, static fn($a, $b) => abs($b) <=> abs($a));
        return (float)($candidates[0] ?? 0);
    }

    private function dashboard_component_reconcile_gap(array $row): float
    {
        $balanceQty = (float)($row['balance_qty'] ?? 0);
        $lotQty = (float)($row['lot_qty'] ?? $balanceQty);
        $candidates = [
            (float)($row['delta_balance_daily'] ?? 0),
            (float)($row['delta_balance_movement'] ?? 0),
            (float)($row['delta_daily_movement'] ?? 0),
            $balanceQty - $lotQty,
        ];
        usort($candidates, static fn($a, $b) => abs($b) <=> abs($a));
        return (float)($candidates[0] ?? 0);
    }

    private function dashboard_stock_breakdown(): array
    {
        $divisionNameColumn = $this->dashboard_division_name_column();
        return [
            'warehouse' => $this->dashboard_stock_breakdown_warehouse(),
            'division' => $this->dashboard_stock_breakdown_division($divisionNameColumn),
            'component' => $this->dashboard_stock_breakdown_component($divisionNameColumn),
        ];
    }

    private function dashboard_stock_breakdown_warehouse(): array
    {
        $query = $this->dashboard_warehouse_monthly_query(
            "SELECT
                COALESCE(NULLIF(MAX(i.item_name), ''), NULLIF(MAX(m.material_name), ''), NULLIF(MAX(s.profile_name), ''), CONCAT('Item #', MAX(s.item_id))) AS item_name,
                SUM(s.closing_qty_content) AS qty,
                MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) AS threshold,
                SUM(COALESCE(s.total_value, 0)) AS total_value,
                'kritis' AS severity
             FROM inv_warehouse_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.identity_key = s.identity_key AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             GROUP BY CASE
                        WHEN COALESCE(s.material_id, i.material_id, 0) > 0 THEN CONCAT('MAT:', COALESCE(s.material_id, i.material_id))
                        ELSE CONCAT('ITEM:', COALESCE(s.item_id, 0))
                      END
             HAVING SUM(s.closing_qty_content) >= 0
                AND SUM(s.closing_qty_content) <= MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0))
             ORDER BY SUM(s.closing_qty_content) ASC, item_name ASC
             LIMIT 20"
        );
        return $query ? $query->result_array() : [];
    }

    private function dashboard_stock_breakdown_division(?string $divisionNameColumn): array
    {
        $locationSelect = $divisionNameColumn
            ? ('COALESCE(d.' . $divisionNameColumn . ', CONCAT(\'Divisi #\', s.division_id))')
            : "CONCAT('Divisi #', s.division_id)";

        $query = $this->dashboard_division_monthly_query(
            "SELECT
                COALESCE(NULLIF(MAX(i.item_name), ''), NULLIF(MAX(m.material_name), ''), NULLIF(MAX(s.profile_name), ''), CONCAT('Item #', MAX(s.item_id))) AS item_name,
                {$locationSelect} AS location_name,
                SUM(s.closing_qty_content) AS qty,
                MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) AS threshold,
                SUM(COALESCE(s.total_value, 0)) AS total_value,
                'kritis' AS severity
             FROM inv_division_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.division_id = s.division_id AND lm.destination_type = s.destination_type
               AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             GROUP BY s.division_id, s.destination_type,
                      CASE
                        WHEN COALESCE(s.material_id, i.material_id, 0) > 0 THEN CONCAT('MAT:', COALESCE(s.material_id, i.material_id))
                        ELSE CONCAT('ITEM:', COALESCE(s.item_id, 0))
                      END
             HAVING SUM(s.closing_qty_content) >= 0
                AND SUM(s.closing_qty_content) <= MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0))
             ORDER BY {$locationSelect} ASC, SUM(s.closing_qty_content) ASC, item_name ASC"
        );
        return $query ? $query->result_array() : [];
    }

    private function dashboard_stock_breakdown_component(?string $divisionNameColumn): array
    {
        $locationSelect = $divisionNameColumn
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.location_type)')
            : 's.location_type';

        $query = $this->dashboard_component_monthly_query(
            "SELECT
                COALESCE(c.component_name, CONCAT('Component #', s.component_id)) AS item_name,
                {$locationSelect} AS location_name,
                s.closing_qty AS qty,
                GREATEST(COALESCE(c.min_stock, 0), 0) AS threshold,
                COALESCE(s.total_value, 0) AS total_value,
                'kritis' AS severity
             FROM inv_component_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.location_type = s.location_type AND lm.division_id <=> s.division_id
               AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key
             LEFT JOIN mst_component c ON c.id = s.component_id
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty >= 0
               AND s.closing_qty <= GREATEST(COALESCE(c.min_stock, 0), 0)
             ORDER BY {$locationSelect} ASC, s.closing_qty ASC, item_name ASC"
        );
        return $query ? $query->result_array() : [];
    }

    private function dashboard_critical_divisions(): array
    {
        if (!$this->dashboard_table_ready('mst_operational_division')) {
            return [];
        }
        $nameCol = $this->db->field_exists('division_name', 'mst_operational_division') ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        if ($nameCol === null) {
            return [];
        }
        return $this->db->select('id, ' . $nameCol . ' AS name')->from('mst_operational_division')
            ->where('is_active', 1)->order_by($nameCol, 'ASC')->get()->result_array();
    }

    private function dashboard_stock_cards(): array
    {
        $cards = [];

        $warehouseSummary = $this->dashboard_warehouse_stock_summary();
        if ($warehouseSummary !== null) {
            $cards[] = [
                'code' => 'warehouse',
                'label' => 'Stok Gudang',
                'total_rows' => (int)($warehouseSummary['total_rows'] ?? 0),
                'critical_count' => (int)($warehouseSummary['critical_count'] ?? 0),
                'total_value' => (float)($warehouseSummary['total_value'] ?? 0),
            ];
        }

        $divisionSummary = $this->dashboard_division_stock_summary();
        if ($divisionSummary !== null) {
            $cards[] = [
                'code' => 'division',
                'label' => 'Stok Divisi',
                'total_rows' => (int)($divisionSummary['total_rows'] ?? 0),
                'critical_count' => (int)($divisionSummary['critical_count'] ?? 0),
                'total_value' => (float)($divisionSummary['total_value'] ?? 0),
            ];
        }

        $componentSummary = $this->dashboard_component_stock_summary();
        if ($componentSummary !== null) {
            $cards[] = [
                'code' => 'component',
                'label' => 'Base/Prepare',
                'total_rows' => (int)($componentSummary['total_rows'] ?? 0),
                'critical_count' => (int)($componentSummary['critical_count'] ?? 0),
                'total_value' => (float)($componentSummary['total_value'] ?? 0),
            ];
        }

        return $cards;
    }

    private function dashboard_critical_stock_rows(int $divisionFilter = 0): array
    {
        $rows = [];
        $divisionNameColumn = $this->dashboard_division_name_column();

        if ($divisionFilter <= 0) {
            $warehouseRows = $this->dashboard_warehouse_critical_rows();
            if (!empty($warehouseRows)) {
                $rows = array_merge($rows, $warehouseRows);
            }
        }

        $divisionRows = $this->dashboard_division_critical_rows($divisionNameColumn, $divisionFilter > 0 ? $divisionFilter : null);
        if (!empty($divisionRows)) {
            $rows = array_merge($rows, $divisionRows);
        }

        if ($divisionFilter <= 0) {
            $rows = array_merge($rows, $this->dashboard_component_critical_rows($divisionNameColumn));
        }

        usort($rows, static function (array $left, array $right): int {
            $leftGap = (float)($left['threshold_qty'] ?? 0) - (float)($left['qty_balance'] ?? 0);
            $rightGap = (float)($right['threshold_qty'] ?? 0) - (float)($right['qty_balance'] ?? 0);
            if ($leftGap === $rightGap) {
                return strcmp((string)($left['item_name'] ?? ''), (string)($right['item_name'] ?? ''));
            }
            return $rightGap <=> $leftGap;
        });

        return array_slice($rows, 0, 10);
    }

    private function dashboard_warehouse_stock_summary(): ?array
    {
        $query = $this->dashboard_warehouse_monthly_query(
            "SELECT COUNT(*) AS total_rows,
                    COALESCE(SUM(CASE
                        WHEN agg.qty_balance >= 0
                         AND agg.qty_balance <= agg.threshold_qty
                        THEN 1 ELSE 0 END), 0) AS critical_count,
                    COALESCE(SUM(agg.total_value), 0) AS total_value
             FROM (
                SELECT
                    SUM(s.closing_qty_content) AS qty_balance,
                    MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) AS threshold_qty,
                    SUM(COALESCE(s.total_value, 0)) AS total_value
                FROM inv_warehouse_monthly_stock s
                INNER JOIN ({latest_month_subquery}) lm
                   ON lm.identity_key = s.identity_key
                  AND lm.month_key = s.month_key
                LEFT JOIN mst_item i ON i.id = s.item_id
                LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
                GROUP BY CASE
                           WHEN COALESCE(s.material_id, i.material_id, 0) > 0 THEN CONCAT('MAT:', COALESCE(s.material_id, i.material_id))
                           ELSE CONCAT('ITEM:', COALESCE(s.item_id, 0))
                         END
             ) agg"
        );

        return $query !== null ? $query->row_array() : null;
    }

    private function dashboard_division_stock_summary(): ?array
    {
        $query = $this->dashboard_division_monthly_query(
            "SELECT COUNT(*) AS total_rows,
                    COALESCE(SUM(CASE
                        WHEN agg.qty_balance >= 0
                         AND agg.qty_balance <= agg.threshold_qty
                        THEN 1 ELSE 0 END), 0) AS critical_count,
                    COALESCE(SUM(agg.total_value), 0) AS total_value
             FROM (
                SELECT
                    SUM(s.closing_qty_content) AS qty_balance,
                    MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) AS threshold_qty,
                    SUM(COALESCE(s.total_value, 0)) AS total_value
                FROM inv_division_monthly_stock s
                INNER JOIN ({latest_month_subquery}) lm
                   ON lm.division_id = s.division_id
                  AND lm.destination_type = s.destination_type
                  AND lm.identity_key = s.identity_key
                  AND lm.month_key = s.month_key
                LEFT JOIN mst_item i ON i.id = s.item_id
                LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
                GROUP BY s.division_id, s.destination_type,
                         CASE
                           WHEN COALESCE(s.material_id, i.material_id, 0) > 0 THEN CONCAT('MAT:', COALESCE(s.material_id, i.material_id))
                           ELSE CONCAT('ITEM:', COALESCE(s.item_id, 0))
                         END
             ) agg"
        );

        return $query !== null ? $query->row_array() : null;
    }

    private function dashboard_warehouse_critical_rows(): array
    {
        $query = $this->dashboard_warehouse_monthly_query(
            "SELECT 'Gudang' AS stock_scope,
                    COALESCE(NULLIF(MAX(i.item_name), ''), NULLIF(MAX(m.material_name), ''), NULLIF(MAX(s.profile_name), ''), CONCAT('Item #', MAX(s.item_id))) AS item_name,
                    'Gudang Pusat' AS location_name,
                    SUM(s.closing_qty_content) AS qty_balance,
                    MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) AS threshold_qty,
                    SUM(COALESCE(s.total_value, 0)) AS total_value
             FROM inv_warehouse_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.identity_key = s.identity_key
               AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             GROUP BY CASE
                        WHEN COALESCE(s.material_id, i.material_id, 0) > 0 THEN CONCAT('MAT:', COALESCE(s.material_id, i.material_id))
                        ELSE CONCAT('ITEM:', COALESCE(s.item_id, 0))
                      END
             HAVING SUM(s.closing_qty_content) >= 0
                AND SUM(s.closing_qty_content) <= MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0))
             ORDER BY (MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) - SUM(s.closing_qty_content)) DESC,
                      SUM(s.closing_qty_content) ASC,
                      item_name ASC
             LIMIT 5"
        );

        return $query !== null ? $query->result_array() : [];
    }

    private function dashboard_division_critical_rows(?string $divisionNameColumn, ?int $divisionId = null): array
    {
        $divisionLocationSelect = $divisionNameColumn !== null
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.destination_type, CONCAT(\'Divisi #\', s.division_id))')
            : "COALESCE(s.destination_type, CONCAT('Divisi #', s.division_id))";

        $divisionWhere = $divisionId !== null ? ' AND s.division_id = ' . (int)$divisionId : '';

        $query = $this->dashboard_division_monthly_query(
            "SELECT 'Divisi' AS stock_scope,
                    COALESCE(NULLIF(MAX(i.item_name), ''), NULLIF(MAX(m.material_name), ''), NULLIF(MAX(s.profile_name), ''), CONCAT('Item #', MAX(s.item_id))) AS item_name,
                    {$divisionLocationSelect} AS location_name,
                    SUM(s.closing_qty_content) AS qty_balance,
                    MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) AS threshold_qty,
                    SUM(COALESCE(s.total_value, 0)) AS total_value
             FROM inv_division_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.division_id = s.division_id
               AND lm.destination_type = s.destination_type
               AND lm.identity_key = s.identity_key
               AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE 1=1 {$divisionWhere}
             GROUP BY s.division_id, s.destination_type,
                      CASE
                        WHEN COALESCE(s.material_id, i.material_id, 0) > 0 THEN CONCAT('MAT:', COALESCE(s.material_id, i.material_id))
                        ELSE CONCAT('ITEM:', COALESCE(s.item_id, 0))
                      END
             HAVING SUM(s.closing_qty_content) >= 0
                AND SUM(s.closing_qty_content) <= MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0))
             ORDER BY (MAX(GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)) - SUM(s.closing_qty_content)) DESC,
                      SUM(s.closing_qty_content) ASC,
                      item_name ASC
             LIMIT 15"
        );

        return $query !== null ? $query->result_array() : [];
    }

    private function dashboard_division_name_column(): ?string
    {
        if (!$this->dashboard_table_ready('mst_operational_division')) {
            return null;
        }

        if ($this->db->field_exists('division_name', 'mst_operational_division')) {
            return 'division_name';
        }

        if ($this->db->field_exists('name', 'mst_operational_division')) {
            return 'name';
        }

        return null;
    }

    private function dashboard_component_stock_summary(): ?array
    {
        $componentQuery = $this->dashboard_component_monthly_query(
            "SELECT COUNT(*) AS total_rows,
                    COALESCE(SUM(CASE
                        WHEN s.closing_qty >= 0
                         AND s.closing_qty <= GREATEST(COALESCE(c.min_stock, 0), 0)
                        THEN 1 ELSE 0 END), 0) AS critical_count,
                    COALESCE(SUM(s.total_value), 0) AS total_value
             FROM inv_component_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.location_type = s.location_type
               AND lm.division_id <=> s.division_id
               AND lm.component_id = s.component_id
               AND lm.uom_id = s.uom_id
               AND lm.month_key = s.month_key
             LEFT JOIN mst_component c ON c.id = s.component_id"
        );

        if ($componentQuery === null) {
            return null;
        }

        return $componentQuery->row_array();
    }

    private function dashboard_component_critical_rows(?string $divisionNameColumn): array
    {
        $componentLocationSelect = $divisionNameColumn !== null
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.location_type, CONCAT(\'Divisi #\', s.division_id))')
            : "COALESCE(s.location_type, CONCAT('Divisi #', s.division_id))";

        $componentQuery = $this->dashboard_component_monthly_query(
            "SELECT 'Base/Prepare' AS stock_scope,
                    COALESCE(c.component_name, CONCAT('Component #', s.component_id)) AS item_name,
                    {$componentLocationSelect} AS location_name,
                    s.closing_qty AS qty_balance,
                    GREATEST(COALESCE(c.min_stock, 0), 0) AS threshold_qty,
                    COALESCE(s.total_value, 0) AS total_value
             FROM inv_component_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.location_type = s.location_type
               AND lm.division_id <=> s.division_id
               AND lm.component_id = s.component_id
               AND lm.uom_id = s.uom_id
               AND lm.month_key = s.month_key
             LEFT JOIN mst_component c ON c.id = s.component_id
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty >= 0
               AND s.closing_qty <= GREATEST(COALESCE(c.min_stock, 0), 0)
             ORDER BY (CASE
                         WHEN s.closing_qty <= 0 THEN GREATEST(COALESCE(c.min_stock, 0), 0)
                         ELSE GREATEST(COALESCE(c.min_stock, 0), 0) - s.closing_qty
                       END) DESC,
                      s.closing_qty ASC
             LIMIT 5"
        );

        return $componentQuery !== null ? $componentQuery->result_array() : [];
    }

    /**
     * Deficit is the live shortage indicator. It is intentionally read from
     * the deficit ledger, not from historical monthly negative balances.
     */
    private function dashboard_active_deficit_rows(): array
    {
        if (!$this->dashboard_table_ready('inv_stock_deficit')) {
            return [];
        }

        $divisionNameColumn = $this->dashboard_division_name_column();
        $divisionNameExpr = $divisionNameColumn !== null
            ? ('MAX(dv.' . $divisionNameColumn . ')')
            : 'NULL';
        $catalogProfileExpr = "''";
        if ($this->dashboard_table_ready('mst_purchase_catalog')
            && $this->db->field_exists('profile_key', 'mst_purchase_catalog')) {
            $catalogNameExpr = $this->db->field_exists('catalog_name', 'mst_purchase_catalog') ? 'NULLIF(pc.catalog_name, \'\')' : 'NULL';
            $catalogBrandExpr = $this->db->field_exists('brand_name', 'mst_purchase_catalog') ? 'NULLIF(pc.brand_name, \'\')' : 'NULL';
            $catalogDescExpr = $this->db->field_exists('line_description', 'mst_purchase_catalog') ? 'NULLIF(pc.line_description, \'\')' : 'NULL';
            $catalogActiveWhere = $this->db->field_exists('is_active', 'mst_purchase_catalog') ? 'AND COALESCE(pc.is_active, 1) = 1' : '';
            $catalogOrder = $this->db->field_exists('last_purchase_date', 'mst_purchase_catalog') ? 'pc.last_purchase_date DESC, pc.id DESC' : 'pc.id DESC';
            $catalogProfileExpr = "COALESCE((SELECT CONCAT_WS(' | ', {$catalogNameExpr}, {$catalogBrandExpr}, {$catalogDescExpr})
                FROM mst_purchase_catalog pc
                WHERE pc.profile_key <=> d.profile_key {$catalogActiveWhere}
                ORDER BY {$catalogOrder}
                LIMIT 1), '')";
        }

        $sql = "SELECT
                    MAX(d.id) AS id,
                    MAX(d.deficit_date) AS last_deficit_date,
                    MAX(COALESCE(d.updated_at, d.created_at)) AS last_activity_at,
                    d.stock_domain,
                    d.location_scope,
                    d.division_id,
                    d.destination_type,
                    d.item_id,
                    d.material_id,
                    d.component_id,
                    d.content_uom_id,
                    d.profile_key,
                    COALESCE(MAX(mi.item_name), MAX(mm.material_name), MAX(mc.component_name), '-') AS inventory_name,
                    {$catalogProfileExpr} AS profile_label,
                    COALESCE({$divisionNameExpr}, NULLIF(d.destination_type, ''), NULLIF(d.location_scope, ''), '-') AS location_name,
                    COALESCE(MAX(u.code), '') AS uom_code,
                    COUNT(*) AS event_count,
                    COALESCE(SUM(d.qty_remaining), 0) AS qty_remaining,
                    COALESCE(SUM(d.qty_remaining * d.estimated_unit_cost), 0) AS estimated_total_value
                 FROM inv_stock_deficit d
                 LEFT JOIN mst_item mi ON mi.id = d.item_id
                 LEFT JOIN mst_material mm ON mm.id = d.material_id
                 LEFT JOIN mst_component mc ON mc.id = d.component_id
                 LEFT JOIN mst_uom u ON u.id = d.content_uom_id
                 LEFT JOIN mst_operational_division dv ON dv.id = d.division_id
                 WHERE d.status = 'OPEN'
                   AND d.qty_remaining > 0.0001
                 GROUP BY d.stock_domain, d.location_scope, d.division_id, d.destination_type,
                          d.item_id, d.material_id, d.component_id, d.content_uom_id, d.profile_key
                 ORDER BY last_activity_at DESC, id DESC
                 LIMIT 100";
        $query = $this->dashboard_safe_query($sql);
        return $query !== null ? $query->result_array() : [];
    }

    private function dashboard_component_monthly_query(string $sql)
    {
        if (!$this->dashboard_table_ready('inv_component_monthly_stock')) {
            return null;
        }

        $targetMonth = $this->db->escape(date('Y-m-01'));
        $latestMonthSubquery = "SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS month_key
                                FROM inv_component_monthly_stock
                                WHERE month_key = (
                                    SELECT MAX(month_key)
                                    FROM inv_component_monthly_stock
                                    WHERE month_key <= {$targetMonth}
                                )
                                GROUP BY location_type, division_id, component_id, uom_id";

        return $this->dashboard_safe_query(str_replace('{latest_month_subquery}', $latestMonthSubquery, $sql));
    }

    private function dashboard_warehouse_monthly_query(string $sql)
    {
        if (!$this->dashboard_table_ready('inv_warehouse_monthly_stock')) {
            return null;
        }

        $targetMonth = $this->db->escape(date('Y-m-01'));
        $latestMonthSubquery = "SELECT identity_key, MAX(month_key) AS month_key
                                FROM inv_warehouse_monthly_stock
                                WHERE month_key = (
                                    SELECT MAX(month_key)
                                    FROM inv_warehouse_monthly_stock
                                    WHERE month_key <= {$targetMonth}
                                )
                                GROUP BY identity_key";

        return $this->dashboard_safe_query(str_replace('{latest_month_subquery}', $latestMonthSubquery, $sql));
    }

    private function dashboard_division_monthly_query(string $sql)
    {
        if (!$this->dashboard_table_ready('inv_division_monthly_stock')) {
            return null;
        }

        $targetMonth = $this->db->escape(date('Y-m-01'));
        $latestMonthSubquery = "SELECT division_id, destination_type, identity_key, MAX(month_key) AS month_key
                                FROM inv_division_monthly_stock
                                WHERE month_key = (
                                    SELECT MAX(month_key)
                                    FROM inv_division_monthly_stock
                                    WHERE month_key <= {$targetMonth}
                                )
                                GROUP BY division_id, destination_type, identity_key";

        return $this->dashboard_safe_query(str_replace('{latest_month_subquery}', $latestMonthSubquery, $sql));
    }

    private function dashboard_table_ready(string $table): bool
    {
        if (array_key_exists($table, $this->dashboardTableReadyCache)) {
            return $this->dashboardTableReadyCache[$table];
        }

        if (!$this->db->table_exists($table)) {
            $this->dashboardTableReadyCache[$table] = false;
            return false;
        }

        $protectedTable = $this->db->protect_identifiers($table, true);
        $probe = $this->dashboard_safe_query('SELECT 1 FROM ' . $protectedTable . ' LIMIT 1');
        $isReady = $probe !== null;
        $this->dashboardTableReadyCache[$table] = $isReady;

        return $isReady;
    }

    // ── Prod Live category config ──────────────────────────────────────
    private function dashboard_load_prod_live_hidden_cats(): array
    {
        $rows = $this->db
            ->select('config_key, config_value')
            ->from('sys_app_config')
            ->where('config_group', 'dashboard')
            ->get()->result_array();

        $result = [];
        foreach ($rows as $r) {
            $key = (string)($r['config_key'] ?? '');
            if (strpos($key, 'prod_live.hidden_cats.') !== 0) continue;
            $div    = substr($key, strlen('prod_live.hidden_cats.'));
            $hidden = json_decode((string)($r['config_value'] ?? '[]'), true);
            $result[$div] = is_array($hidden) ? $hidden : [];
        }
        return $result;
    }

    public function save_prod_live_cats(): void
    {
        if (!$this->input->is_ajax_request() && $this->input->method() !== 'post') { show_404(); return; }

        $division   = trim((string)($this->input->post('division') ?? ''));
        $hiddenRaw  = $this->input->post('hidden_cats');
        $hiddenCats = is_array($hiddenRaw) ? array_values(array_map('strval', $hiddenRaw)) : [];

        if ($division === '') { $this->json_error('division wajib.'); return; }

        $configKey = 'prod_live.hidden_cats.' . $division;
        $existing  = $this->db->select('id')
            ->from('sys_app_config')
            ->where('config_group', 'dashboard')
            ->where('config_key', $configKey)
            ->limit(1)->get()->row_array();

        $now    = date('Y-m-d H:i:s');
        $userId = (int)($this->current_user['id'] ?? 0);

        if ($existing) {
            $this->db->update('sys_app_config', [
                'config_value' => json_encode($hiddenCats),
                'updated_by'   => $userId ?: null,
                'updated_at'   => $now,
            ], ['id' => (int)$existing['id']]);
        } else {
            $this->db->insert('sys_app_config', [
                'config_group' => 'dashboard',
                'config_key'   => $configKey,
                'config_value' => json_encode($hiddenCats),
                'description'  => 'Kategori tersembunyi Stok Produk Live POS — ' . $division,
                'updated_by'   => $userId ?: null,
                'updated_at'   => $now,
            ]);
        }

        $this->json_ok(['saved' => true, 'division' => $division, 'hidden_count' => count($hiddenCats)]);
    }

    private function json_ok(array $data = []): void
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true, 'data' => $data]));
    }

    private function json_error(string $message, int $statusCode = 400): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message]));
    }

    private function dashboard_safe_query(string $sql)
    {
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;

        try {
            $query = $this->db->query($sql);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if ($query === false) {
            $error = $this->db->error();
            if (!empty($error['code']) || !empty($error['message'])) {
                log_message('error', 'Dashboard query failed: [' . ($error['code'] ?? 0) . '] ' . ($error['message'] ?? 'unknown database error'));
            }
            return null;
        }

        return $query;
    }

    private function dashboard_recent_activity(array $filters): array
    {
        $rows = [];

        if ($this->dashboard_table_ready('pos_order')) {
            $posRows = $this->db
                ->select('order_no AS ref_no, status, grand_total AS amount, COALESCE(paid_at, ordered_at, created_at) AS event_at', false)
                ->select($this->db->escape('POS') . ' AS source_label', false)
                ->from('pos_order')
                ->where('DATE(COALESCE(paid_at, ordered_at, created_at)) >=', $filters['date_from'])
                ->where('DATE(COALESCE(paid_at, ordered_at, created_at)) <=', $filters['date_to'])
                ->order_by('event_at', 'DESC')
                ->limit(6)
                ->get()->result_array();
            $rows = array_merge($rows, $posRows);
        }

        if ($this->dashboard_table_ready('pur_purchase_order')) {
            $poRows = $this->db
                ->select('po_no AS ref_no, status, grand_total AS amount, CONCAT(request_date, " 00:00:00") AS event_at', false)
                ->select($this->db->escape('PURCHASE') . ' AS source_label', false)
                ->from('pur_purchase_order')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->order_by('request_date', 'DESC')
                ->limit(6)
                ->get()->result_array();
            $rows = array_merge($rows, $poRows);
        }

        if ($this->dashboard_table_ready('inv_stock_movement_log')) {
            $movementRows = $this->db
                ->select('movement_no AS ref_no, movement_type AS status, ABS(qty_content_delta * unit_cost) AS amount, CONCAT(movement_date, " 00:00:00") AS event_at', false)
                ->select($this->db->escape('INVENTORY') . ' AS source_label', false)
                ->from('inv_stock_movement_log')
                ->where('movement_date >=', $filters['date_from'])
                ->where('movement_date <=', $filters['date_to'])
                ->order_by('movement_date', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(6)
                ->get()->result_array();
            $rows = array_merge($rows, $movementRows);
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string)($right['event_at'] ?? ''), (string)($left['event_at'] ?? ''));
        });

        return array_slice($rows, 0, 10);
    }
}
