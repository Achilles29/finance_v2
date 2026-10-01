<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory_division extends Purchase
{
    private const PAGE_OPNAME = 'inventory.stock.opname.division.index';
    private $json_response_buffer_started = false;

    public function index()     { parent::stock_division_index(); }
    public function opening()   { parent::stock_opening_division_index(); }
    public function adjustment(){ parent::stock_adjustment_division_index(); }
    public function transfer()  { parent::stock_transfer_division_index(); }
    public function daily()     { parent::stock_division_daily_index(); }
    public function compare()   { parent::stock_division_reconcile_index(); }
    public function reconcile_audit()  { parent::stock_division_reconcile_audit(); }
    public function reconcile_repair()     { parent::stock_division_reconcile_repair(); }
    public function reconcile_lot_repair()        { parent::stock_division_reconcile_lot_repair(); }
    public function reconcile_lot_profile_sync()  { parent::stock_division_reconcile_lot_profile_sync(); }
    public function reconcile_lot_repair_all()    { parent::stock_division_reconcile_lot_repair_all(); }
    public function reconcile_gap_repair_all()    { parent::stock_division_reconcile_gap_repair_all(); }
    public function reconcile_lot_only_adjust()   { parent::stock_division_reconcile_lot_only_adjust(); }
    public function reconcile_log_repair()        { parent::stock_division_reconcile_log_repair(); }
    public function reconcile_repair_material_id() { parent::stock_division_reconcile_repair_material_id(); }
    public function reconcile_profile_repair()     { parent::stock_division_reconcile_profile_repair(); }
    public function reconcile_profile_merge()      { parent::stock_division_reconcile_profile_merge(); }
    public function movement()  { parent::stock_division_movement_index(); }
    public function stok_awal() { parent::stock_opening_division_generated(); }
    public function material_matrix() { parent::stock_material_daily_matrix(); }
    public function matrix_view()     { parent::inventory_material_daily_index(); }
    public function lot()       { parent::division_lot_audit_index(); }

    // =========================================================
    // Opname Stok Bahan Baku Harian Divisi
    // =========================================================

    public function opname()
    {
        $this->require_permission(self::PAGE_OPNAME, 'view');

        $opnameDate = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $divisionId  = (int)$this->input->get('division_id', true);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destination === '') {
            $destination = 'ALL';
        }
        $q = trim((string)$this->input->get('q', true));
        $profileKey = trim((string)$this->input->get('profile_key', true));

        $isSuperadmin = !empty($this->current_user['is_superadmin']);
        $canCreate    = $isSuperadmin || $this->can(self::PAGE_OPNAME, 'create');
        $divisions    = $this->Purchase_model->list_active_operational_divisions();
        if ($scopeDivisionId !== null) {
            $divisions = array_values(array_filter($divisions, static function (array $row) use ($scopeDivisionId): bool {
                return (int)($row['id'] ?? 0) === $scopeDivisionId;
            }));
        }

        $this->render('inventory/stock_opname_division_index', [
            'title'       => 'Daily Recon Bahan Baku Divisi',
            'active_menu' => 'purchase.stock.opname.division',
            'opname_date' => $opnameDate,
            'division_id' => $divisionId,
            'destination' => $destination,
            'q'           => $q,
            'profile_key' => $profileKey,
            'divisions'   => $divisions,
            'can_create'  => $canCreate,
            'division_scope_id' => $scopeDivisionId,
        ]);
    }

    public function opname_data()
    {
        $this->require_permission(self::PAGE_OPNAME, 'view');

        $opnameDate  = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $divisionId  = (int)$this->input->get('division_id', true);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        $q           = trim((string)$this->input->get('q', true));
        $profileKey  = trim((string)$this->input->get('profile_key', true));

        $targetMonth = date('Y-m-01', strtotime($opnameDate));

        $divNameCol  = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divNameExpr = $divNameCol ? ('dv.' . $divNameCol) : 'CAST(dms.division_id AS CHAR)';

        // Subquery tidak pakai alias -- gunakan column name langsung
        $subDivWhere = $divisionId > 0 ? 'AND division_id = ' . (int)$divisionId : '';

        $latestSub = "SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
                      FROM inv_division_monthly_stock
                      WHERE month_key = " . $this->db->escape($targetMonth) . "
                      {$subDivWhere}
                      GROUP BY division_id, destination_type, identity_key";

        $destWhere = '';
        if ($destination !== 'ALL') {
            if ($destination === 'REGULER') {
                $destWhere = "AND dms.destination_type NOT IN ('BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT')";
            } elseif ($destination === 'EVENT') {
                $destWhere = "AND dms.destination_type IN ('BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT')";
            } else {
                $destWhere = "AND dms.destination_type = " . $this->db->escape($destination);
            }
        }

        $qWhere = '';
        if ($q !== '') {
            $qLike = $this->db->escape('%' . $q . '%');
            $qWhere = "AND (i.item_name LIKE {$qLike} OR m.material_name LIKE {$qLike}
                          OR i.item_code LIKE {$qLike} OR m.material_code LIKE {$qLike}
                          OR dms.profile_name LIKE {$qLike})";
        }
        if ($profileKey !== '') {
            // A deficit link needs to open the exact purchasing profile, not
            // every similarly named material in the division.
            $profileKeyEsc = $this->db->escape($profileKey);
            $qWhere .= ' AND (COALESCE(dms.profile_key, \'\') = ' . $profileKeyEsc
                . ' OR COALESCE(dms.identity_key, \'\') = ' . $profileKeyEsc . ')';
        }

        $divWhereMain = $divisionId > 0 ? 'AND dms.division_id = ' . (int)$divisionId : '';

        $hasCatTable  = $this->db->table_exists('mst_item_category');
        $catJoin      = $hasCatTable ? 'LEFT JOIN mst_item_category cat ON cat.id = m.item_category_id' : '';
        $catNameExpr  = $hasCatTable ? "COALESCE(cat.name, '')" : "''";
        $catIdExpr    = $hasCatTable ? 'COALESCE(cat.id, 0)' : '0';
        $catOrderExpr = $hasCatTable ? 'COALESCE(cat.id, 99999),' : '';
        $matActiveWhere = $this->db->field_exists('is_active', 'mst_material')
            ? 'AND (m.id IS NULL OR COALESCE(m.is_active, 1) = 1)'
            : '';
        // Daily recon is stock-ledger based. Legacy/inactive items can still own current
        // monthly stock after cutoff generation, so filtering mst_item.is_active here hides
        // real stock and makes recon look like zero.
        $itemActiveWhere = '';
        $lotStartEsc = $this->db->escape($targetMonth);
        $lotEndEsc   = $this->db->escape($opnameDate);

        $sql = "
            SELECT
                dms.division_id,
                {$divNameExpr}                                AS division_name,
                dms.destination_type,
                dms.item_id,
                dms.material_id,
                dms.buy_uom_id,
                dms.content_uom_id,
                COALESCE(dms.profile_key, '')                 AS profile_key,
                COALESCE(dms.identity_key, dms.profile_key, '') AS identity_key,
                COALESCE(dms.profile_name, m.material_name, i.item_name, '') AS profile_name,
                COALESCE(dms.profile_brand, '')               AS profile_brand,
                COALESCE(dms.profile_description, '')         AS profile_description,
                dms.profile_expired_date,
                COALESCE(dms.profile_content_per_buy, 1)      AS profile_content_per_buy,
                COALESCE(dms.profile_buy_uom_code, '')        AS profile_buy_uom_code,
                COALESCE(dms.profile_content_uom_code, '')    AS profile_content_uom_code,
                COALESCE(i.item_code, '')                     AS item_code,
                COALESCE(i.item_name, '')                     AS item_name,
                COALESCE(m.material_code, '')                 AS material_code,
                COALESCE(m.material_name, '')                 AS material_name,
                dms.closing_qty_content                       AS system_qty_content,
                dms.closing_qty_buy                           AS system_qty_buy,
                dms.avg_cost_per_content,
                dms.total_value,
                dms.last_movement_date,
                (
                    SELECT MIN(l.receipt_date)
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.division_id = dms.division_id
                      AND l.destination_type = dms.destination_type
                      AND COALESCE(l.item_id, 0) = COALESCE(dms.item_id, 0)
                      AND COALESCE(l.material_id, li.material_id, 0) = COALESCE(dms.material_id, i.material_id, 0)
                      AND COALESCE(l.content_uom_id, 0) = COALESCE(dms.content_uom_id, 0)
                      AND COALESCE(l.profile_key, '') = COALESCE(dms.profile_key, '')
                      AND l.status = 'OPEN'
                      AND l.qty_balance > 0.0001
                      AND l.receipt_date >= {$lotStartEsc}
                      AND l.receipt_date <= {$lotEndEsc}
                ) AS stock_in_first_date,
                (
                    SELECT MAX(l.receipt_date)
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.division_id = dms.division_id
                      AND l.destination_type = dms.destination_type
                      AND COALESCE(l.item_id, 0) = COALESCE(dms.item_id, 0)
                      AND COALESCE(l.material_id, li.material_id, 0) = COALESCE(dms.material_id, i.material_id, 0)
                      AND COALESCE(l.content_uom_id, 0) = COALESCE(dms.content_uom_id, 0)
                      AND COALESCE(l.profile_key, '') = COALESCE(dms.profile_key, '')
                      AND l.status = 'OPEN'
                      AND l.qty_balance > 0.0001
                      AND l.receipt_date >= {$lotStartEsc}
                      AND l.receipt_date <= {$lotEndEsc}
                ) AS stock_in_last_date,
                (
                    SELECT GROUP_CONCAT(DISTINCT COALESCE(l.source_table, '') ORDER BY COALESCE(l.source_table, '') SEPARATOR ',')
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.division_id = dms.division_id
                      AND l.destination_type = dms.destination_type
                      AND COALESCE(l.item_id, 0) = COALESCE(dms.item_id, 0)
                      AND COALESCE(l.material_id, li.material_id, 0) = COALESCE(dms.material_id, i.material_id, 0)
                      AND COALESCE(l.content_uom_id, 0) = COALESCE(dms.content_uom_id, 0)
                      AND COALESCE(l.profile_key, '') = COALESCE(dms.profile_key, '')
                      AND l.status = 'OPEN'
                      AND l.qty_balance > 0.0001
                      AND l.receipt_date >= {$lotStartEsc}
                      AND l.receipt_date <= {$lotEndEsc}
                ) AS stock_in_sources,
                {$catNameExpr}                                AS category_name,
                {$catIdExpr}                                  AS category_id,
                0                                             AS is_recipe_only
            FROM inv_division_monthly_stock dms
            INNER JOIN ({$latestSub}) lm
                ON  lm.division_id      = dms.division_id
                AND lm.destination_type = dms.destination_type
                AND lm.identity_key     = dms.identity_key
                AND lm.max_month        = dms.month_key
            LEFT JOIN mst_item     i  ON i.id = dms.item_id
            LEFT JOIN mst_material m  ON m.id = COALESCE(dms.material_id, i.material_id)
            LEFT JOIN mst_operational_division dv ON dv.id = dms.division_id
            {$catJoin}
            WHERE dms.material_id IS NOT NULL
              AND (dms.profile_key IS NULL OR dms.identity_key = dms.profile_key)
              {$matActiveWhere}
              {$itemActiveWhere}
              {$divWhereMain}
              {$destWhere}
              {$qWhere}
            ORDER BY {$divNameExpr},
                     {$catOrderExpr}
                     COALESCE(m.material_name, i.item_name),
                     dms.profile_name
        ";

        $prevDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $r = $this->db->query($sql);
        $this->db->db_debug = $prevDbDebug;
        if (!$r) {
            $err = $this->db->error();
            $this->jsonError('Gagal memuat data daily recon bahan baku: ' . (string)($err['message'] ?? 'query database gagal'), 500);
            return;
        }
        $stockRows = $r->result_array();

        // A current shortage can exist before the profile has a monthly row.
        // When Daily Recon was opened from Defisit Stok, append only that exact
        // open profile as a zero-system row so the operator can count it.
        $this->append_open_deficit_profile_to_daily_recon_rows(
            $stockRows,
            $divisionId,
            $destination,
            $profileKey
        );

        // Zero-balance profiles still need a meaningful cost reference when a
        // physical count finds stock. Prefer the exact catalog profile price.
        $this->enrich_material_daily_recon_catalog_costs($stockRows);
        $this->attach_material_daily_recon_flags($stockRows, $opnameDate, $divisionId);

        // â”€â”€ Append recipe-only materials (active in recipes but no stock record) â”€â”€
        $includeRecipeOnlyRows = false;
        if ($includeRecipeOnlyRows && $destination !== 'EVENT' && $this->db->table_exists('mst_product_recipe')) {
            $existingKeys = [];
            foreach ($stockRows as $stockR) {
                if (!empty($stockR['material_id']) && !empty($stockR['division_id'])) {
                    $existingKeys[(int)$stockR['division_id'] . '|' . (int)$stockR['material_id']] = true;
                }
            }

            $recDivNameExpr = $divNameCol ? ('dv.' . $divNameCol) : 'CAST(r.source_division_id AS CHAR)';
            $recCatJoin     = $hasCatTable ? 'LEFT JOIN mst_item_category cat ON cat.id = m.item_category_id' : '';
            $recCatExpr     = $hasCatTable ? "COALESCE(MIN(cat.name), '')" : "''";
            $recCatIdExpr   = $hasCatTable ? 'MIN(COALESCE(cat.id, 0))' : '0';

            $matIsActive  = $this->db->field_exists('is_active', 'mst_material') ? 'AND m.is_active = 1' : '';
            $itemIsActive = $this->db->field_exists('is_active', 'mst_item')     ? 'AND i.is_active = 1' : '';

            $recDivWhere = $divisionId > 0 ? 'AND r.source_division_id = ' . (int)$divisionId : '';
            $recQWhere   = '';
            if ($q !== '') {
                $qLike = $this->db->escape('%' . $q . '%');
                $recQWhere = "AND (m.material_name LIKE {$qLike} OR m.material_code LIKE {$qLike})";
            }

            $recSql = "
                SELECT
                    r.source_division_id            AS division_id,
                    MIN({$recDivNameExpr})           AS division_name,
                    'OTHER'                          AS destination_type,
                    MIN(i.id)                        AS item_id,
                    m.id                             AS material_id,
                    m.content_uom_id                 AS buy_uom_id,
                    m.content_uom_id                 AS content_uom_id,
                    ''                               AS profile_key,
                    ''                               AS identity_key,
                    m.material_name                  AS profile_name,
                    ''                               AS profile_brand,
                    ''                               AS profile_description,
                    NULL                             AS profile_expired_date,
                    1                                AS profile_content_per_buy,
                    MIN(u.code)                      AS profile_buy_uom_code,
                    MIN(u.code)                      AS profile_content_uom_code,
                    MIN(i.item_code)                 AS item_code,
                    MIN(i.item_name)                 AS item_name,
                    m.material_code                  AS material_code,
                    m.material_name                  AS material_name,
                    0                                AS system_qty_content,
                    0                                AS system_qty_buy,
                    0                                AS avg_cost_per_content,
                    0                                AS total_value,
                    NULL                             AS last_movement_date,
                    NULL                             AS stock_in_first_date,
                    NULL                             AS stock_in_last_date,
                    NULL                             AS stock_in_sources,
                    {$recCatExpr}                    AS category_name,
                    {$recCatIdExpr}                  AS category_id,
                    1                                AS is_recipe_only
                FROM mst_product_recipe r
                JOIN mst_item i ON i.id = r.material_item_id {$itemIsActive}
                JOIN mst_material m ON m.id = i.material_id {$matIsActive}
                JOIN mst_operational_division dv ON dv.id = r.source_division_id
                LEFT JOIN mst_uom u ON u.id = m.content_uom_id
                {$recCatJoin}
                WHERE r.source_division_id IS NOT NULL
                  AND r.material_item_id IS NOT NULL
                  AND i.material_id IS NOT NULL
                  {$recDivWhere}
                  {$recQWhere}
                GROUP BY r.source_division_id, m.id, m.content_uom_id, m.material_code, m.material_name
            ";

            $addedAny = false;
            $recRows  = ($rq = $this->db->query($recSql)) ? $rq->result_array() : [];
            foreach ($recRows as $rRec) {
                $key = (int)$rRec['division_id'] . '|' . (int)$rRec['material_id'];
                if (!isset($existingKeys[$key])) {
                    $stockRows[]        = $rRec;
                    $existingKeys[$key] = true;
                    $addedAny           = true;
                }
            }

            if ($addedAny) {
                usort($stockRows, static function (array $a, array $b): int {
                    $dc = strcmp((string)($a['division_name'] ?? ''), (string)($b['division_name'] ?? ''));
                    if ($dc !== 0) return $dc;
                    $catA = (int)($a['category_id'] ?? 0);
                    $catB = (int)($b['category_id'] ?? 0);
                    if ($catA !== $catB) return $catA <=> $catB;
                    return strcmp((string)($a['material_name'] ?? ''), (string)($b['material_name'] ?? ''));
                });
            }
        }

        // Load opname records for this date (all or specific division)
        $opnameQuery = $this->db->table_exists('inv_division_stock_opname')
            ? $this->db->select('division_id, destination_type, identity_key, physical_qty_content, notes, adjustment_id')
                ->from('inv_division_stock_opname')
                ->where('opname_date', $opnameDate)
            : null;
        if ($opnameQuery && $divisionId > 0) {
            $opnameQuery->where('division_id', $divisionId);
        }
        $opnameRows = $opnameQuery ? $opnameQuery->get()->result_array() : [];

        $opnameMap = [];
        foreach ($opnameRows as $row) {
            $opnameMap[$this->division_opname_profile_key($row)] = $row;
        }

        // Group: division -> material -> profiles
        $divisionGroups = [];
        foreach ($stockRows as $r) {
            $divId   = (int)$r['division_id'];
            $divName = (string)$r['division_name'];
            $destKey = strtoupper((string)($r['destination_type'] ?? 'OTHER'));
            $matKey  = $divId . '|' . $destKey . '|' . ($r['material_id'] ?: ('item_' . $r['item_id']));
            $ikey    = (string)$r['identity_key'];
            $opname  = $opnameMap[$this->division_opname_profile_key($r)] ?? null;

            $profile = [
                'division_id'         => $divId,
                'destination_type'    => $r['destination_type'],
                'item_id'             => (int)$r['item_id'],
                'material_id'         => (int)$r['material_id'],
                'buy_uom_id'          => (int)$r['buy_uom_id'],
                'content_uom_id'      => (int)$r['content_uom_id'],
                'profile_key'         => $r['profile_key'],
                'identity_key'        => $ikey,
                'profile_name'        => $r['profile_name'],
                'profile_brand'       => $r['profile_brand'],
                'profile_description' => $r['profile_description'],
                'profile_expired_date'=> $r['profile_expired_date'],
                'profile_content_per_buy'  => (float)$r['profile_content_per_buy'],
                'profile_buy_uom_code'     => $r['profile_buy_uom_code'],
                'profile_content_uom_code' => $r['profile_content_uom_code'],
                'item_code'           => $r['item_code'],
                'item_name'           => $r['item_name'],
                'material_code'       => $r['material_code'],
                'material_name'       => $r['material_name'],
                'system_qty_content'  => (float)$r['system_qty_content'],
                'system_qty_buy'      => (float)$r['system_qty_buy'],
                'avg_cost_per_content'=> (float)$r['avg_cost_per_content'],
                'total_value'         => (float)$r['total_value'],
                'last_movement_date'  => (string)($r['last_movement_date'] ?? ''),
                'stock_in_first_date' => (string)($r['stock_in_first_date'] ?? ''),
                'stock_in_last_date'  => (string)($r['stock_in_last_date'] ?? ''),
                'stock_in_sources'    => (string)($r['stock_in_sources'] ?? ''),
                'category_name'       => (string)($r['category_name'] ?? ''),
                'is_recipe_only'      => !empty($r['is_recipe_only']),
                // Keep the exact profile metadata from the server. It is also
                // used by a virtual row opened from Defisit Stok.
                'is_deficit_virtual'  => !empty($r['is_deficit_virtual']),
                'deficit_qty_remaining' => (float)($r['deficit_qty_remaining'] ?? 0),
                'catalog_avg_cost_per_content' => (float)($r['catalog_avg_cost_per_content'] ?? 0),
                'cost_reference_source' => (string)($r['cost_reference_source'] ?? ''),
                'physical_qty_content'=> $opname !== null && array_key_exists('physical_qty_content', $opname)
                    && $opname['physical_qty_content'] !== null
                    ? (float)$opname['physical_qty_content'] : null,
                'selisih'             => null,
                'opname_notes'        => (string)($opname['notes'] ?? ''),
                'adjustment_id'       => ($opname && !empty($opname['adjustment_id']))
                    ? (int)$opname['adjustment_id'] : null,
                'lot_count'            => (int)($r['lot_count'] ?? 0),
                'recon_line_key'       => (string)($r['recon_line_key'] ?? ''),
                'must_row_confirm'     => !empty($r['must_row_confirm']),
                'must_row_confirm_reason' => (string)($r['must_row_confirm_reason'] ?? ''),
                'confirmed_open'       => !empty($r['confirmed_open']),
                'confirmed_close'      => !empty($r['confirmed_close']),
            ];

            if (!isset($divisionGroups[$divId])) {
                $divisionGroups[$divId] = [
                    'division_id'   => $divId,
                    'division_name' => $divName,
                    'materials'     => [],
                ];
            }
            if (!isset($divisionGroups[$divId]['materials'][$matKey])) {
                $divisionGroups[$divId]['materials'][$matKey] = [
                    'material_id'   => (int)$r['material_id'],
                    'material_code' => $r['material_code'],
                    'material_name' => $r['material_name'] ?: $r['item_name'],
                    'destination_type' => $r['destination_type'],
                    'item_id'          => (int)$r['item_id'],
                    'content_uom_code' => $r['profile_content_uom_code'],
                    'category_name'    => (string)($r['category_name'] ?? ''),
                    'profiles'         => [],
                    'system_total'     => 0.0,
                    'physical_total'   => null,
                ];
            }
            $divisionGroups[$divId]['materials'][$matKey]['profiles'][] = $profile;
            $divisionGroups[$divId]['materials'][$matKey]['system_total'] += $profile['system_qty_content'];
            if ($profile['physical_qty_content'] !== null) {
                $divisionGroups[$divId]['materials'][$matKey]['physical_total'] =
                    (($divisionGroups[$divId]['materials'][$matKey]['physical_total'] ?? 0) + $profile['physical_qty_content']);
            }
        }

        // Flatten materials for each division
        $result = [];
        foreach ($divisionGroups as $divRow) {
            $divRow['materials'] = array_values($divRow['materials']);
            $result[] = $divRow;
        }

        $totalMaterials = array_sum(array_map(fn($d) => count($d['materials']), $result));
        $totalProfiles  = count($stockRows);

        $this->output->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'   => true,
                'rows' => $result,
                'meta' => [
                    'opname_date'      => $opnameDate,
                    'total_divisions'  => count($result),
                    'total_materials'  => $totalMaterials,
                    'total_profiles'   => $totalProfiles,
                    'confirm_mode'     => $this->daily_recon_confirm_mode(),
                ],
            ]));
    }

    public function opname_save_physical()
    {
        $this->require_permission(self::PAGE_OPNAME, 'create');
        $this->begin_json_response();

        $payload     = $this->request_payload();
        $opnameDate  = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $divisionId  = (int)($payload['division_id'] ?? 0);
        $destination = strtoupper(trim((string)($payload['destination_type'] ?? 'OTHER')));
        $identKey    = trim((string)($payload['identity_key'] ?? ''));
        $physQty     = isset($payload['physical_qty_content']) && $payload['physical_qty_content'] !== ''
            ? round((float)$payload['physical_qty_content'], 4) : null;
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['id'] ?? 0);

        // The request may wait behind another long-running screen when the
        // browser keeps the same PHP session lock. Recon does not write any
        // session value after permission has been checked, so release it.
        $this->release_request_session_lock();

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $this->jsonError('Tanggal recon tidak valid.', 422);
            return;
        }
        if ($divisionId <= 0 || $identKey === '') {
            $this->jsonError('division_id dan identity_key wajib diisi.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_division_stock_opname')) {
            $this->jsonError('Tabel opname belum ada. Jalankan SQL setup terlebih dahulu.', 500);
            return;
        }

        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->load->library('InventoryPeriodGuard');
            $period = $this->inventoryperiodguard->ensureActiveMonthOpen(
                'MATERIAL',
                $opnameDate,
                $userId > 0 ? $userId : null,
                'Automatic material period from daily recon'
            );
            if (!($period['ok'] ?? false)) {
                $this->jsonError((string)($period['message'] ?? 'Periode stok tidak dapat dipakai.'), 409);
                return;
            }
        }

        $snapshot = $this->current_material_recon_snapshot(
            $opnameDate,
            $divisionId,
            $destination,
            $identKey,
            (int)($payload['content_uom_id'] ?? 0)
        );
        if (!($snapshot['ok'] ?? false)) {
            $this->jsonError((string)($snapshot['message'] ?? 'Profil stok tidak dapat diverifikasi.'), 409);
            return;
        }
        $identityError = $this->validate_material_recon_payload_identity($payload, $snapshot);
        if ($identityError !== null) {
            $this->jsonError($identityError, 409);
            return;
        }

        // A direct recon can confirm an open deficit even when the current
        // stock already equals the physical count (for example, a batch or
        // receipt was posted after the original POS shortage). No zero-value
        // adjustment is created; only the auditable deficit settlement is.
        $settlementOnlyRequested = !empty($payload['settle_open_deficit'])
            && strtoupper(trim((string)($payload['input_mode'] ?? ''))) === 'PHYSICAL_COUNT'
            && array_key_exists('physical_qty_content', $payload);
        if ($settlementOnlyRequested) {
            $physicalQty = round((float)($payload['physical_qty_content'] ?? 0), 4);
            $systemQty = round((float)($snapshot['system_qty_content'] ?? 0), 4);
            if ($physicalQty < -0.0001) {
                $this->jsonError('Stok fisik tidak boleh bernilai negatif.', 422);
                return;
            }
            if (abs($physicalQty - $systemQty) <= 0.0001) {
                if ($physicalQty <= 0.0001 || !file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
                    $this->jsonError('Tidak ada stok fisik yang dapat dipakai untuk menyelesaikan defisit ini.', 422);
                    return;
                }
                $this->load->library('InventoryDeficitService');
                $settlement = $this->inventorydeficitservice->settle([
                    'stock_domain' => 'MATERIAL',
                    'deficit_date' => $opnameDate,
                    'location_scope' => 'DIVISION',
                    'division_id' => $divisionId,
                    'destination_type' => $destination,
                    'item_id' => $snapshot['item_id'] ?? null,
                    'material_id' => $snapshot['material_id'] ?? null,
                    'buy_uom_id' => $snapshot['buy_uom_id'] ?? null,
                    'content_uom_id' => $snapshot['content_uom_id'] ?? null,
                    'profile_key' => $snapshot['profile_key'] ?? $identKey,
                    'qty_available' => $physicalQty,
                    'estimated_unit_cost' => (float)($snapshot['avg_cost_per_content'] ?? 0),
                    'source_module' => 'INVENTORY_RECON',
                    'source_table' => 'inventory_deficit_recon',
                    'source_id' => (int)($payload['deficit_id'] ?? 0) ?: null,
                    'notes' => 'Recon fisik mengonfirmasi penyelesaian defisit tanpa perubahan saldo.',
                    'created_by' => (int)($this->current_user['id'] ?? 0) ?: null,
                ]);
                if (!($settlement['ok'] ?? false)) {
                    $this->jsonError((string)($settlement['message'] ?? 'Gagal menyelesaikan defisit dari hasil recon.'), 422);
                    return;
                }
                if ((float)($settlement['settled_qty'] ?? 0) <= 0.0001) {
                    $this->jsonError('Tidak ditemukan defisit terbuka yang cocok dengan profil stok ini.', 422);
                    return;
                }
                $this->jsonOk([
                    'settlement_only' => true,
                    'settled_qty' => (float)($settlement['settled_qty'] ?? 0),
                ], 'Stok sistem sudah sama dengan hitungan fisik. Defisit ditutup sebagai hasil recon tanpa membuat adjustment baru.');
                return;
            }
        }

        $profileValues = $this->material_recon_snapshot_profile_values($snapshot, $identKey);

        $systemQty = (float)($snapshot['system_qty_content'] ?? 0);
        $selisih   = $physQty !== null ? round($physQty - $systemQty, 4) : null;

        $existing = $this->db
            ->where('opname_date',     $opnameDate)
            ->where('division_id',     $divisionId)
            ->where('destination_type',$destination)
            ->where('identity_key',    $identKey)
            ->get('inv_division_stock_opname')->row_array();

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('inv_division_stock_opname', [
                'physical_qty_content' => $physQty,
                'system_qty_content'   => $systemQty,
                'notes'                => $notes !== '' ? $notes : null,
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->insert('inv_division_stock_opname', [
                'opname_date'             => $opnameDate,
                'division_id'             => $divisionId,
                'destination_type'        => $destination,
                'item_id'                 => $profileValues['item_id'],
                'material_id'             => $profileValues['material_id'],
                'buy_uom_id'              => $profileValues['buy_uom_id'],
                'content_uom_id'          => $profileValues['content_uom_id'],
                'profile_key'             => $profileValues['profile_key'],
                'identity_key'            => $identKey,
                'profile_name'            => $profileValues['profile_name'],
                'profile_content_per_buy' => $profileValues['profile_content_per_buy'],
                'profile_buy_uom_code'    => $profileValues['profile_buy_uom_code'],
                'profile_content_uom_code'=> $profileValues['profile_content_uom_code'],
                'system_qty_content'      => $systemQty,
                'physical_qty_content'    => $physQty,
                'notes'                   => $notes !== '' ? $notes : null,
                'created_by'              => $userId > 0 ? $userId : null,
            ]);
        }

        $this->jsonOk([
            'selisih' => $selisih,
            'physical_qty_content' => $physQty,
            'system_qty_content' => $systemQty,
        ]);
    }

    public function opname_quick_adjust()
    {
        $this->require_permission(self::PAGE_OPNAME, 'create');
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'create');
        $this->begin_json_response();

        $payload     = $this->request_payload();
        $opnameDate  = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $divisionId  = (int)($payload['division_id'] ?? 0);
        $destination = strtoupper(trim((string)($payload['destination_type'] ?? 'OTHER')));
        $identKey    = trim((string)($payload['identity_key'] ?? ''));

        if ($divisionId <= 0 || $identKey === '') {
            $this->jsonError('division_id dan identity_key wajib diisi.', 422);
            return;
        }

        // The request can post a FIFO and ledger transaction. No session value
        // is changed after permission validation, so release the lock before
        // the database work starts.
        $this->release_request_session_lock();

        $snapshot = $this->current_material_recon_snapshot(
            $opnameDate,
            $divisionId,
            $destination,
            $identKey,
            (int)($payload['content_uom_id'] ?? 0)
        );
        if (!($snapshot['ok'] ?? false)) {
            $this->jsonError((string)($snapshot['message'] ?? 'Profil stok tidak dapat diverifikasi.'), 409);
            return;
        }
        $identityError = $this->validate_material_recon_payload_identity($payload, $snapshot);
        if ($identityError !== null) {
            $this->jsonError($identityError, 409);
            return;
        }

        // A physical count may confirm that an exact-profile deficit has been
        // covered by stock already on hand. In that zero-delta case no draft
        // adjustment is needed; settle only the matching deficit ledger.
        $inputMode = strtoupper(trim((string)($payload['input_mode'] ?? '')));
        $settleOpenDeficit = $inputMode === 'PHYSICAL_COUNT' && !empty($payload['settle_open_deficit']);
        $physicalProvided = array_key_exists('physical_qty_content', $payload)
            && $payload['physical_qty_content'] !== '';
        if ($settleOpenDeficit && $physicalProvided) {
            $physicalQty = round((float)$payload['physical_qty_content'], 4);
            $systemQty = round((float)($snapshot['system_qty_content'] ?? 0), 4);
            if ($physicalQty < -0.0001) {
                $this->jsonError('Stok fisik tidak boleh bernilai negatif.', 422);
                return;
            }
            if (abs($physicalQty - $systemQty) <= 0.0001) {
                if ($physicalQty <= 0.0001 || !file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
                    $this->jsonError('Defisit tidak dapat diselesaikan dari stok fisik nol.', 422);
                    return;
                }
                $this->load->library('InventoryDeficitService');
                if (!$this->inventorydeficitservice->isReady()) {
                    $this->jsonError('Fondasi defisit stok belum siap. Jalankan SQL inventory terbaru terlebih dahulu.', 409);
                    return;
                }
                $settlement = $this->inventorydeficitservice->settle([
                    'stock_domain' => 'MATERIAL',
                    'deficit_date' => $opnameDate,
                    'location_scope' => 'DIVISION',
                    'division_id' => $divisionId,
                    'destination_type' => $destination,
                    'item_id' => $snapshot['item_id'] ?? null,
                    'material_id' => $snapshot['material_id'] ?? null,
                    'buy_uom_id' => $snapshot['buy_uom_id'] ?? null,
                    'content_uom_id' => $snapshot['content_uom_id'] ?? null,
                    'profile_key' => $snapshot['profile_key'] ?? $identKey,
                    'qty_available' => $physicalQty,
                    'estimated_unit_cost' => (float)($snapshot['avg_cost_per_content'] ?? 0),
                    'source_module' => 'INVENTORY_RECON',
                    'source_table' => 'inventory_deficit_recon',
                    'source_id' => (int)($payload['deficit_id'] ?? 0) ?: null,
                    'notes' => 'Recon fisik mengonfirmasi penyelesaian defisit tanpa perubahan saldo.',
                    'created_by' => (int)($this->current_user['id'] ?? 0) ?: null,
                ]);
                if (!($settlement['ok'] ?? false)) {
                    $this->jsonError((string)($settlement['message'] ?? 'Gagal menyelesaikan defisit dari hasil recon.'), 422);
                    return;
                }
                if ((float)($settlement['settled_qty'] ?? 0) <= 0.0001) {
                    $this->jsonError('Tidak ditemukan defisit terbuka dengan barang, area, UOM, dan profil yang sama.', 422);
                    return;
                }
                $this->jsonOk([
                    'settlement_only' => true,
                    'settled_qty' => (float)($settlement['settled_qty'] ?? 0),
                    'adjustment_id' => 0,
                ], 'Stok sistem sudah sesuai dengan hitungan fisik. Defisit yang profilnya sama berhasil ditutup tanpa adjustment baru.');
                return;
            }
        }

        $profileValues = $this->material_recon_snapshot_profile_values($snapshot, $identKey);

        $this->load->library('InventoryAdjustmentIntent');
        $intent = $this->inventoryadjustmentintent->resolve(
            $payload,
            'MATERIAL',
            (float)($snapshot['system_qty_content'] ?? 0)
        );
        if (!($intent['ok'] ?? false)) {
            $this->jsonError((string)($intent['message'] ?? 'Parameter adjustment tidak lengkap.'), 409);
            return;
        }

        $physQty     = (float)($intent['physical_qty'] ?? 0);
        $systemQty   = (float)($intent['system_qty'] ?? 0);
        $selisih     = (float)($intent['delta_qty'] ?? 0);
        $adjType     = strtoupper(trim((string)($payload['adjustment_type'] ?? '')));
        $reasonCode  = strtolower(trim((string)($payload['reason_code'] ?? 'other')));
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['id'] ?? 0);

        // Jenis penyesuaian harus sesuai sistem adjustment divisi yang ada
        $validNeg = ['WASTE', 'SPOIL', 'PROCESS_LOSS', 'VARIANCE', 'SPOILAGE', 'ADJUSTMENT_MINUS'];
        $validPos = ['ADJUSTMENT_PLUS'];

        if ($selisih < 0 && !in_array($adjType, $validNeg, true)) {
            $adjType = 'ADJUSTMENT_MINUS';
        }
        if ($selisih > 0 && !in_array($adjType, $validPos, true)) {
            $adjType = 'ADJUSTMENT_PLUS';
        }

        $rc     = $reasonCode !== '' ? $reasonCode : 'other';
        $adjNo  = 'OPN-' . date('Ymd', strtotime($opnameDate)) . '-' . strtoupper(substr(md5($identKey . $opnameDate), 0, 6));
        $absQty = round(abs($selisih), 4);

        $isPhysicalCount = strtoupper(trim((string)($intent['input_mode'] ?? ''))) === 'PHYSICAL_COUNT';
        // Regular Daily Recon has one purpose: record the counted final stock.
        // When that count adds stock, settle only an exact-profile deficit. The
        // dedicated deficit modal can still explicitly leave it open.
        $hasSettlementChoice = array_key_exists('settle_open_deficit', $payload);
        $settleOpenDeficit = $isPhysicalCount
            && ($hasSettlementChoice ? !empty($payload['settle_open_deficit']) : $selisih > 0.0001);
        $line = array_merge($profileValues, [
            'input_mode'           => (string)($intent['input_mode'] ?? 'PHYSICAL_COUNT'),
            'settle_open_deficit'  => $settleOpenDeficit ? 1 : 0,
            'settle_open_deficit_qty_content' => $settleOpenDeficit ? max(0, $physQty) : 0,
            'system_qty_snapshot_content' => (float)($intent['system_qty'] ?? $systemQty),
            'physical_qty_snapshot_content' => ($intent['input_mode'] ?? '') === 'PHYSICAL_COUNT'
                ? (float)($intent['physical_qty'] ?? $physQty)
                : null,
            'note'                 => $notes,
        ]);

        if ($selisih < 0) {
            $reasonMap = [
                'WASTE'            => ['qty_waste_content'        => $absQty, 'waste_reason_code'        => $rc],
                'SPOIL'            => ['qty_spoil_content'        => $absQty, 'spoil_reason_code'        => $rc],
                'SPOILAGE'         => ['qty_spoil_content'        => $absQty, 'spoil_reason_code'        => $rc],
                'PROCESS_LOSS'     => ['qty_process_loss_content' => $absQty, 'process_loss_reason_code' => $rc],
                'VARIANCE'         => ['qty_variance_content'     => $absQty, 'variance_reason_code'     => $rc],
                'ADJUSTMENT_MINUS' => ['qty_variance_content'     => $absQty, 'variance_reason_code'     => $rc],
            ];
            $line = array_merge($line, $reasonMap[$adjType] ?? ['qty_variance_content' => $absQty, 'variance_reason_code' => $rc]);
        } else {
            $unitCost = (float)($snapshot['avg_cost_per_content'] ?? 0);
            $line['qty_adjustment_plus_content'] = $absQty;
            $line['adjustment_plus_reason_code'] = $rc;
            $line['unit_cost']                   = $unitCost > 0 ? $unitCost : null;
        }

        // Daily Recon posts the physical count first; the model then aligns FIFO lots in the same transaction.
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->save_stock_adjustment([
                'id'               => 0,
                'adjustment_no'    => $adjNo,
                'adjustment_date'  => $opnameDate,
                'stock_scope'      => 'DIVISION',
                'division_id'      => $divisionId,
                'destination_type' => $destination,
                'notes'            => 'Daily recon (' . strtolower((string)$intent['input_mode']) . ')' . ($notes !== '' ? ': ' . $notes : ''),
            ], [$line], $userId);

            if (!($result['ok'] ?? false)) {
                $this->jsonError((string)($result['message'] ?? 'Gagal menyimpan adjustment.'), 422);
                return;
            }

            $adjId = (int)($result['id'] ?? 0);
            $post  = $this->Purchase_model->post_stock_adjustment($adjId, $userId);
            if (!($post['ok'] ?? false)) {
                $this->jsonError('Tersimpan sebagai draft, tetapi gagal posting: ' . (string)($post['message'] ?? ''), 422);
                return;
            }
        } catch (Throwable $e) {
            log_message('error', 'daily recon material adjustment failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonError('Daily Recon gagal diproses di server: ' . $e->getMessage(), 500);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        $opnameRecorded = true;
        $opnameWarning = '';
        if ($this->db->table_exists('inv_division_stock_opname') && $adjId > 0) {
            // The stock adjustment is already POSTED. Never let a secondary
            // opname-audit write turn that success into an HTML DB error.
            $dbDebugBefore = (bool)$this->db->db_debug;
            $this->db->db_debug = false;
            try {
                $existing = $this->db
                    ->select('id')
                    ->where('opname_date',      $opnameDate)
                    ->where('division_id',      $divisionId)
                    ->where('destination_type', $destination)
                    ->where('identity_key',     $identKey)
                    ->limit(1)
                    ->get('inv_division_stock_opname')
                    ->row_array();

                // inv_division_stock_opname intentionally stores a compact
                // audit shape. Do not pass the broader adjustment profile
                // payload here because newer profile fields are not columns
                // on this historical audit table.
                $opnameRow = [
                    'opname_date'          => $opnameDate,
                    'division_id'          => $divisionId,
                    'destination_type'     => $destination,
                    'item_id'              => $profileValues['item_id'],
                    'material_id'          => $profileValues['material_id'],
                    'buy_uom_id'           => $profileValues['buy_uom_id'],
                    'content_uom_id'       => $profileValues['content_uom_id'],
                    'profile_key'          => $profileValues['profile_key'],
                    'identity_key'         => $identKey,
                    'profile_name'         => $profileValues['profile_name'],
                    'profile_content_per_buy' => $profileValues['profile_content_per_buy'],
                    'profile_buy_uom_code' => $profileValues['profile_buy_uom_code'],
                    'profile_content_uom_code' => $profileValues['profile_content_uom_code'],
                    'system_qty_content'   => $systemQty,
                    'physical_qty_content' => $physQty,
                    'notes'                => $notes !== '' ? $notes : null,
                    'adjustment_id'        => $adjId,
                ];

                $saved = !empty($existing['id'])
                    ? $this->db->where('id', (int)$existing['id'])->update('inv_division_stock_opname', $opnameRow)
                    : $this->db->insert('inv_division_stock_opname', $opnameRow + ['created_by' => $userId > 0 ? $userId : null]);
                if (!$saved) {
                    $dbError = $this->db->error();
                    throw new RuntimeException((string)($dbError['message'] ?? 'gagal menyimpan jejak opname'));
                }
            } catch (Throwable $e) {
                $opnameRecorded = false;
                $opnameWarning = $e->getMessage();
                log_message('error', 'daily recon material audit write failed after adjustment #' . $adjId . ': ' . $opnameWarning);
            } finally {
                $this->db->db_debug = $dbDebugBefore;
            }
        }

        $message = 'Adjustment berhasil diposting.';
        if (!$opnameRecorded) {
            $message .= ' Stok dan lot sudah berubah, tetapi jejak Daily Recon gagal disimpan. Periksa log server.';
        }
        $this->jsonOk([
            'adjustment_id' => $adjId,
            'opname_recorded' => $opnameRecorded,
            'opname_warning' => $opnameWarning,
        ], $message);
    }

    public function opname_confirm_recon()
    {
        $this->require_permission(self::PAGE_OPNAME, 'create');

        $payload = $this->request_payload();
        $date = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->jsonError('Tanggal recon tidak valid.', 422);
            return;
        }

        $divisionId = (int)($payload['division_id'] ?? 0);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $stage = strtoupper(trim((string)($payload['stage'] ?? '')));
        $scope = strtoupper(trim((string)($payload['scope'] ?? 'ALL')));
        $notes = trim((string)($payload['notes'] ?? ''));
        $userId = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));

        if ($divisionId <= 0) {
            $this->jsonError('Pilih satu divisi terlebih dahulu sebelum konfirmasi recon.', 422);
            return;
        }
        if (!in_array($stage, ['OPEN', 'CLOSE'], true)) {
            $this->jsonError('Tahap recon harus OPEN atau CLOSE.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_daily_recon_checkpoint')) {
            $this->jsonError('Tabel checkpoint daily recon belum tersedia. Jalankan SQL setup 2026-07-05a dulu.', 500);
            return;
        }

        if ($scope === 'ROW') {
            if (!$this->db->table_exists('inv_daily_recon_checkpoint_line')) {
                $this->jsonError('Tabel detail checkpoint daily recon belum tersedia. Jalankan SQL setup 2026-07-05a dulu.', 500);
                return;
            }
            $lineKey = trim((string)($payload['line_key'] ?? ''));
            if ($lineKey === '') {
                $this->jsonError('line_key wajib diisi untuk konfirmasi per baris.', 422);
                return;
            }
            $this->upsert_daily_recon_checkpoint_line($date, 'MATERIAL', $divisionId, $stage, [
                'line_key' => $lineKey,
                'line_label' => trim((string)($payload['line_label'] ?? '')),
                'item_id' => !empty($payload['item_id']) ? (int)$payload['item_id'] : null,
                'material_id' => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
                'profile_key' => trim((string)($payload['profile_key'] ?? '')),
                'required_reason' => trim((string)($payload['required_reason'] ?? '')),
                'source_page' => 'inventory/stock/daily-recon/division',
                'notes' => $notes,
                'confirmed_by' => $userId,
            ]);
            $this->jsonOk([
                'opname_date' => $date,
                'division_id' => $divisionId,
                'stage' => $stage,
                'line_key' => $lineKey,
            ], 'Baris bahan baku berhasil dikonfirmasi.');
            return;
        }

        $this->upsert_daily_recon_checkpoint($date, 'MATERIAL', $divisionId, $stage, 'inventory/stock/daily-recon/division', $notes, $userId);
        $this->jsonOk([
            'opname_date' => $date,
            'division_id' => $divisionId,
            'stage' => $stage,
        ], 'Konfirmasi daily recon bahan baku berhasil disimpan.');
    }

    private function upsert_daily_recon_checkpoint(string $date, string $domain, int $divisionId, string $stage, string $sourcePage, string $notes, int $userId): void
    {
        $existing = $this->db->select('id')
            ->from('inv_daily_recon_checkpoint')
            ->where('checkpoint_date', $date)
            ->where('recon_domain', $domain)
            ->where('division_id', $divisionId)
            ->where('checkpoint_stage', $stage)
            ->limit(1)
            ->get()
            ->row_array();

        $row = [
            'checkpoint_date' => $date,
            'recon_domain' => $domain,
            'division_id' => $divisionId,
            'checkpoint_stage' => $stage,
            'source_page' => $sourcePage,
            'notes' => $notes !== '' ? $notes : null,
            'confirmed_by' => $userId > 0 ? $userId : null,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($existing['id'])) {
            $this->db->where('id', (int)$existing['id'])->update('inv_daily_recon_checkpoint', $row);
            return;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('inv_daily_recon_checkpoint', $row);
    }

    private function upsert_daily_recon_checkpoint_line(string $date, string $domain, int $divisionId, string $stage, array $line): void
    {
        $lineKey = trim((string)($line['line_key'] ?? ''));
        if ($lineKey === '') {
            return;
        }

        $existing = $this->db->select('id')
            ->from('inv_daily_recon_checkpoint_line')
            ->where('checkpoint_date', $date)
            ->where('recon_domain', $domain)
            ->where('division_id', $divisionId)
            ->where('checkpoint_stage', $stage)
            ->where('line_key', $lineKey)
            ->limit(1)
            ->get()
            ->row_array();

        $row = [
            'checkpoint_date' => $date,
            'recon_domain' => $domain,
            'division_id' => $divisionId,
            'checkpoint_stage' => $stage,
            'line_key' => $lineKey,
            'line_label' => trim((string)($line['line_label'] ?? '')),
            'item_id' => $line['item_id'] ?? null,
            'material_id' => $line['material_id'] ?? null,
            'profile_key' => trim((string)($line['profile_key'] ?? '')) ?: null,
            'required_reason' => trim((string)($line['required_reason'] ?? '')) ?: null,
            'source_page' => trim((string)($line['source_page'] ?? '')),
            'notes' => trim((string)($line['notes'] ?? '')) ?: null,
            'confirmed_by' => !empty($line['confirmed_by']) ? (int)$line['confirmed_by'] : null,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($existing['id'])) {
            $this->db->where('id', (int)$existing['id'])->update('inv_daily_recon_checkpoint_line', $row);
            return;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('inv_daily_recon_checkpoint_line', $row);
    }

    private function attach_material_daily_recon_flags(array &$rows, string $date, int $divisionId): void
    {
        if (empty($rows)) {
            return;
        }

        $requiredTokens = $this->daily_recon_required_tokens('pos.daily_recon_required_materials');
        $lineKeys = [];
        $materialIds = [];
        foreach ($rows as $idx => &$row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId > 0) {
                $materialIds[$materialId] = $materialId;
            }
            $lineKey = $this->material_recon_line_key($row);
            $row['recon_line_key'] = $lineKey;
            $lineKeys[$lineKey] = $lineKey;
        }
        unset($row);

        $lotCounts = [];
        $lotMeta = [];
        $lotsByKey = [];
        if (!empty($materialIds) && $this->db->table_exists('inv_material_fifo_lot')) {
            $lotMonthStart = date('Y-m-01', strtotime($date));
            // Jangan pakai Query Builder where_in untuk daftar besar. CI3 akan memproses
            // SQL panjang dengan regex internal dan bisa gagal "regular expression is too large".
            foreach (array_chunk(array_values($materialIds), 120) as $materialChunk) {
                $materialChunk = array_values(array_filter(array_map('intval', $materialChunk), static function (int $id): bool {
                    return $id > 0;
                }));
                if (empty($materialChunk)) {
                    continue;
                }

                $divWhere = $divisionId > 0 ? 'AND l.division_id = ' . (int)$divisionId : '';
                $lotSql = "
                    SELECT
                        l.division_id,
                        COALESCE(l.destination_type, 'OTHER') AS destination_type,
                        COALESCE(l.item_id, 0) AS item_id,
                        COALESCE(l.material_id, li.material_id, 0) AS material_id,
                        COALESCE(l.buy_uom_id, 0) AS buy_uom_id,
                        COALESCE(l.content_uom_id, 0) AS content_uom_id,
                        COALESCE(l.profile_key, '') AS profile_key,
                        COUNT(*) AS lot_count,
                        MIN(l.receipt_date) AS first_receipt_date,
                        MAX(l.receipt_date) AS last_receipt_date,
                        GROUP_CONCAT(DISTINCT COALESCE(l.source_table, '') ORDER BY COALESCE(l.source_table, '') SEPARATOR ',') AS source_tables
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.status = 'OPEN'
                      AND ABS(COALESCE(l.qty_balance, 0)) > 0.0001
                      AND l.receipt_date >= " . $this->db->escape($lotMonthStart) . "
                      AND l.receipt_date <= " . $this->db->escape($date) . "
                      AND COALESCE(l.material_id, li.material_id) IN (" . implode(',', $materialChunk) . ")
                      {$divWhere}
                    GROUP BY
                        l.division_id,
                        COALESCE(l.destination_type, 'OTHER'),
                        COALESCE(l.item_id, 0),
                        COALESCE(l.material_id, li.material_id, 0),
                        COALESCE(l.buy_uom_id, 0),
                        COALESCE(l.content_uom_id, 0),
                        COALESCE(l.profile_key, '')
                ";
                $lotQuery = $this->db->query($lotSql);
                foreach ($lotQuery ? $lotQuery->result_array() : [] as $lotRow) {
                    $lotKey = $this->material_lot_count_key($lotRow);
                    $lotCounts[$lotKey] = (int)($lotRow['lot_count'] ?? 0);
                    $lotMeta[$lotKey] = [
                        'first_receipt_date' => (string)($lotRow['first_receipt_date'] ?? ''),
                        'last_receipt_date'  => (string)($lotRow['last_receipt_date'] ?? ''),
                        'source_tables'      => (string)($lotRow['source_tables'] ?? ''),
                    ];
                }

                $lotDetailSql = "
                    SELECT
                        l.id AS lot_id,
                        l.lot_no,
                        l.receipt_date,
                        l.source_table,
                        l.source_id,
                        l.division_id,
                        COALESCE(l.destination_type, 'OTHER') AS destination_type,
                        COALESCE(l.item_id, 0) AS item_id,
                        COALESCE(l.material_id, li.material_id, 0) AS material_id,
                        COALESCE(l.buy_uom_id, 0) AS buy_uom_id,
                        COALESCE(l.content_uom_id, 0) AS content_uom_id,
                        COALESCE(l.profile_key, '') AS profile_key,
                        l.qty_balance,
                        l.unit_cost,
                        ROUND(COALESCE(l.qty_balance, 0) * COALESCE(l.unit_cost, 0), 2) AS total_value
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.status = 'OPEN'
                      AND ABS(COALESCE(l.qty_balance, 0)) > 0.0001
                      AND l.receipt_date >= " . $this->db->escape($lotMonthStart) . "
                      AND l.receipt_date <= " . $this->db->escape($date) . "
                      AND COALESCE(l.material_id, li.material_id) IN (" . implode(',', $materialChunk) . ")
                      {$divWhere}
                    ORDER BY l.receipt_date ASC, l.id ASC
                ";
                $lotDetailQuery = $this->db->query($lotDetailSql);
                foreach ($lotDetailQuery ? $lotDetailQuery->result_array() : [] as $lotRow) {
                    $lotKey = $this->material_lot_count_key($lotRow);
                    if (!isset($lotsByKey[$lotKey])) {
                        $lotsByKey[$lotKey] = [];
                    }
                    $lotsByKey[$lotKey][] = [
                        'lot_id'       => (int)($lotRow['lot_id'] ?? 0),
                        'lot_no'       => (string)($lotRow['lot_no'] ?? ''),
                        'receipt_date' => (string)($lotRow['receipt_date'] ?? ''),
                        'source_table' => (string)($lotRow['source_table'] ?? ''),
                        'source_id'    => (int)($lotRow['source_id'] ?? 0),
                        'qty_balance'  => (float)($lotRow['qty_balance'] ?? 0),
                        'unit_cost'    => (float)($lotRow['unit_cost'] ?? 0),
                        'total_value'  => (float)($lotRow['total_value'] ?? 0),
                    ];
                }
            }
        }

        $confirmed = [];
        if (!empty($lineKeys) && $this->db->table_exists('inv_daily_recon_checkpoint_line')) {
            foreach (array_chunk(array_values($lineKeys), 40) as $lineKeyChunk) {
                $lineKeyChunk = array_values(array_filter(array_map(static function ($key): string {
                    return trim((string)$key);
                }, $lineKeyChunk), static function (string $key): bool {
                    return $key !== '';
                }));
                if (empty($lineKeyChunk)) {
                    continue;
                }

                $lineKeySql = implode(',', array_map([$this->db, 'escape'], $lineKeyChunk));
                $lineSql = "
                    SELECT line_key, checkpoint_stage
                    FROM inv_daily_recon_checkpoint_line
                    WHERE checkpoint_date = " . $this->db->escape($date) . "
                      AND recon_domain = 'MATERIAL'
                      " . ($divisionId > 0 ? 'AND division_id = ' . (int)$divisionId : '') . "
                      AND line_key IN ({$lineKeySql})
                ";
                $lineQuery = $this->db->query($lineSql);
                foreach ($lineQuery ? $lineQuery->result_array() : [] as $lineRow) {
                    $confirmed[(string)$lineRow['line_key'] . '|' . strtoupper((string)$lineRow['checkpoint_stage'])] = true;
                }
            }
        }

        $confirmMode = $this->daily_recon_confirm_mode();
        foreach ($rows as &$row) {
            $lotKey = $this->material_lot_count_key($row);
            $lotCount = $lotCounts[$lotKey] ?? 0;
            if (isset($lotMeta[$lotKey])) {
                $row['stock_in_first_date'] = (string)($lotMeta[$lotKey]['first_receipt_date'] ?? ($row['stock_in_first_date'] ?? ''));
                $row['stock_in_last_date']  = (string)($lotMeta[$lotKey]['last_receipt_date'] ?? ($row['stock_in_last_date'] ?? ''));
                $row['stock_in_sources']    = (string)($lotMeta[$lotKey]['source_tables'] ?? ($row['stock_in_sources'] ?? ''));
            }
            $reasons = [];
            if ($confirmMode === 'ROW_REQUIRED') {
                $reasons[] = 'mode wajib satu per satu';
            }
            if ($lotCount > 1) {
                $reasons[] = $lotCount . ' lot aktif';
            }
            if ($this->daily_recon_token_matches($requiredTokens, [
                (string)($row['material_id'] ?? ''),
                (string)($row['material_code'] ?? ''),
                (string)($row['material_name'] ?? ''),
                (string)($row['profile_name'] ?? ''),
            ])) {
                $reasons[] = 'daftar wajib recon';
            }

            $lineKey = (string)($row['recon_line_key'] ?? '');
            $row['lot_count'] = $lotCount;
            $row['lots'] = $lotsByKey[$lotKey] ?? [];
            $row['must_row_confirm'] = !empty($reasons);
            $row['must_row_confirm_reason'] = implode(', ', array_unique($reasons));
            $row['confirmed_open'] = isset($confirmed[$lineKey . '|OPEN']);
            $row['confirmed_close'] = isset($confirmed[$lineKey . '|CLOSE']);
        }
        unset($row);
    }

    private function material_recon_line_key(array $row): string
    {
        return implode('|', [
            'M',
            (int)($row['division_id'] ?? 0),
            strtoupper((string)($row['destination_type'] ?? 'OTHER')),
            (int)($row['item_id'] ?? 0),
            (int)($row['material_id'] ?? 0),
            (int)($row['buy_uom_id'] ?? 0),
            (int)($row['content_uom_id'] ?? 0),
            (string)($row['identity_key'] ?? ($row['profile_key'] ?? '')),
        ]);
    }

    /**
     * Daily Recon normally reads the active monthly balance. A newly recorded
     * shortage can precede that balance row, however. In that narrow case we
     * show a synthetic zero-system row only when the page was opened for the
     * exact open deficit profile.
     */
    private function append_open_deficit_profile_to_daily_recon_rows(
        array &$stockRows,
        int $divisionId,
        string $destinationType,
        string $profileKey
    ): void {
        $destinationType = strtoupper(trim($destinationType));
        $profileKey = trim($profileKey);
        if ($divisionId <= 0 || $profileKey === ''
            || in_array($destinationType, ['ALL', 'REGULER', 'EVENT'], true)
        ) {
            return;
        }

        foreach ($stockRows as $row) {
            if ((int)($row['division_id'] ?? 0) === $divisionId
                && strtoupper((string)($row['destination_type'] ?? '')) === $destinationType
                && trim((string)($row['profile_key'] ?? '')) === $profileKey
            ) {
                return;
            }
        }

        $deficit = $this->find_open_material_deficit_recon_profile(
            $divisionId,
            $destinationType,
            $profileKey
        );
        if ($deficit === null) {
            return;
        }

        $stockRows[] = [
            'division_id' => $divisionId,
            'division_name' => (string)($deficit['division_name'] ?? $divisionId),
            'destination_type' => $destinationType,
            'item_id' => (int)($deficit['item_id'] ?? 0),
            'material_id' => (int)($deficit['material_id'] ?? 0),
            'buy_uom_id' => (int)($deficit['buy_uom_id'] ?? 0),
            'content_uom_id' => (int)($deficit['content_uom_id'] ?? 0),
            'profile_key' => $profileKey,
            'identity_key' => $profileKey,
            'profile_name' => (string)($deficit['profile_name'] ?? ''),
            'profile_brand' => (string)($deficit['profile_brand'] ?? ''),
            'profile_description' => (string)($deficit['profile_description'] ?? ''),
            'profile_expired_date' => null,
            'profile_content_per_buy' => (float)($deficit['profile_content_per_buy'] ?? 1),
            'profile_buy_uom_code' => (string)($deficit['profile_buy_uom_code'] ?? ''),
            'profile_content_uom_code' => (string)($deficit['profile_content_uom_code'] ?? ''),
            'item_code' => (string)($deficit['item_code'] ?? ''),
            'item_name' => (string)($deficit['item_name'] ?? ''),
            'material_code' => (string)($deficit['material_code'] ?? ''),
            'material_name' => (string)($deficit['material_name'] ?? ''),
            'system_qty_content' => 0.0,
            'system_qty_buy' => 0.0,
            'avg_cost_per_content' => (float)($deficit['avg_cost_per_content'] ?? 0),
            'total_value' => 0.0,
            'last_movement_date' => (string)($deficit['last_deficit_date'] ?? ''),
            'stock_in_first_date' => null,
            'stock_in_last_date' => null,
            'stock_in_sources' => null,
            'category_name' => '',
            'category_id' => 0,
            'is_recipe_only' => false,
            'is_deficit_virtual' => true,
            'deficit_qty_remaining' => (float)($deficit['qty_remaining'] ?? 0),
            'catalog_avg_cost_per_content' => (float)($deficit['catalog_avg_cost_per_content'] ?? 0),
            'cost_reference_source' => (string)($deficit['cost_reference_source'] ?? ''),
        ];
    }

    /**
     * Returns one exact open MATERIAL deficit identity with its current
     * catalog price. This is intentionally not a general "add new profile"
     * lookup; it only supports a direct recon from Defisit Stok.
     */
    private function find_open_material_deficit_recon_profile(
        int $divisionId,
        string $destinationType,
        string $profileKey,
        int $contentUomId = 0
    ): ?array {
        if ($divisionId <= 0 || trim($profileKey) === ''
            || !$this->db->table_exists('inv_stock_deficit')
        ) {
            return null;
        }

        $destinationType = strtoupper(trim($destinationType));
        $query = $this->db
            ->select('MAX(d.id) AS deficit_id', false)
            ->select('MAX(d.deficit_date) AS last_deficit_date', false)
            ->select('MAX(COALESCE(d.updated_at, d.created_at)) AS last_activity_at', false)
            ->select('MAX(d.item_id) AS item_id', false)
            ->select('MAX(d.material_id) AS material_id', false)
            ->select('MAX(d.buy_uom_id) AS buy_uom_id', false)
            ->select('MAX(d.content_uom_id) AS content_uom_id', false)
            ->select('MAX(COALESCE(i.item_code, \'\')) AS item_code', false)
            ->select('MAX(COALESCE(i.item_name, \'\')) AS item_name', false)
            ->select('MAX(COALESCE(m.material_code, \'\')) AS material_code', false)
            ->select('MAX(COALESCE(m.material_name, \'\')) AS material_name', false)
            ->select('MAX(COALESCE(dv.name, \'\')) AS division_name', false)
            ->select('MAX(COALESCE(bu.code, \'\')) AS profile_buy_uom_code', false)
            ->select('MAX(COALESCE(cu.code, \'\')) AS profile_content_uom_code', false)
            ->select('COALESCE(SUM(d.qty_remaining), 0) AS qty_remaining', false)
            ->select("CASE WHEN COALESCE(SUM(d.qty_remaining), 0) > 0.0001
                THEN COALESCE(SUM(d.qty_remaining * d.estimated_unit_cost), 0) / SUM(d.qty_remaining)
                ELSE 0 END AS deficit_avg_cost_per_content", false)
            ->from('inv_stock_deficit d')
            ->join('mst_item i', 'i.id = d.item_id', 'left')
            ->join('mst_material m', 'm.id = d.material_id', 'left')
            ->join('mst_operational_division dv', 'dv.id = d.division_id', 'left')
            ->join('mst_uom bu', 'bu.id = d.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = d.content_uom_id', 'left')
            ->where('d.stock_domain', 'MATERIAL')
            ->where('d.status', 'OPEN')
            ->where('d.location_scope', 'DIVISION')
            ->where('d.division_id', $divisionId)
            ->where('d.destination_type', $destinationType)
            ->where('d.profile_key', trim($profileKey));
        if ($contentUomId > 0) {
            $query->where('d.content_uom_id', $contentUomId);
        }

        $row = $query
            ->group_by([
                'd.division_id', 'd.destination_type', 'd.item_id', 'd.material_id',
                'd.buy_uom_id', 'd.content_uom_id', 'd.profile_key',
            ])
            ->having('SUM(COALESCE(d.qty_remaining, 0)) > 0.0001', null, false)
            ->order_by('last_activity_at', 'DESC', false)
            ->limit(1)
            ->get()
            ->row_array();
        if (empty($row)) {
            return null;
        }

        $catalog = $this->load_material_catalog_profiles([trim($profileKey)])[trim($profileKey)] ?? [];
        $profileContentPerBuy = max(0.000001, (float)($catalog['content_per_buy'] ?? 1));
        $catalogCost = round((float)($catalog['avg_cost_per_content'] ?? 0), 6);
        $deficitCost = round((float)($row['deficit_avg_cost_per_content'] ?? 0), 6);

        $row['profile_name'] = trim((string)($catalog['catalog_name'] ?? ''))
            ?: (string)($row['item_name'] ?? $row['material_name'] ?? '');
        $row['profile_brand'] = trim((string)($catalog['brand_name'] ?? ''));
        $row['profile_description'] = trim((string)($catalog['line_description'] ?? ''));
        $row['profile_content_per_buy'] = $profileContentPerBuy;
        $row['catalog_avg_cost_per_content'] = $catalogCost;
        $row['avg_cost_per_content'] = $catalogCost > 0 ? $catalogCost : $deficitCost;
        $row['cost_reference_source'] = $catalogCost > 0
            ? (string)($catalog['price_source'] ?? 'Harga katalog')
            : ($deficitCost > 0 ? 'Estimasi biaya defisit' : '');

        return $row;
    }

    /**
     * Adds the exact catalog cost to zero-cost monthly profiles. The current
     * stock value remains unchanged; this only gives a safe default for a
     * later adjustment plus or physical-count posting.
     */
    private function enrich_material_daily_recon_catalog_costs(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }

        $profileKeys = [];
        foreach ($rows as $row) {
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $profileKeys[$profileKey] = $profileKey;
            }
        }
        $catalogByProfile = $this->load_material_catalog_profiles(array_values($profileKeys));
        foreach ($rows as &$row) {
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            $catalog = $catalogByProfile[$profileKey] ?? null;
            if (!$catalog) {
                $row['cost_reference_source'] = $row['cost_reference_source'] ?? '';
                continue;
            }

            $catalogCost = round((float)($catalog['avg_cost_per_content'] ?? 0), 6);
            $row['catalog_avg_cost_per_content'] = $catalogCost;
            if ((float)($row['avg_cost_per_content'] ?? 0) <= 0.000001 && $catalogCost > 0) {
                $row['avg_cost_per_content'] = $catalogCost;
                $row['cost_reference_source'] = (string)($catalog['price_source'] ?? 'Harga katalog');
            } elseif ((float)($row['avg_cost_per_content'] ?? 0) > 0.000001) {
                $row['cost_reference_source'] = $row['cost_reference_source'] ?? 'Rata-rata stok aktif';
            }
        }
        unset($row);
    }

    /** @return array<string, array> */
    private function load_material_catalog_profiles(array $profileKeys): array
    {
        $profileKeys = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string)$value);
        }, $profileKeys), static function (string $value): bool {
            return $value !== '';
        })));
        if (empty($profileKeys) || !$this->db->table_exists('mst_purchase_catalog')) {
            return [];
        }

        $query = $this->db
            ->select('c.id, c.profile_key, c.item_id, c.material_id, c.catalog_name, c.brand_name, c.line_description')
            ->select('COALESCE(NULLIF(c.content_per_buy, 0), 1) AS content_per_buy', false)
            ->select('COALESCE(c.last_unit_price, 0) AS last_unit_price', false)
            ->select('COALESCE(c.standard_price, 0) AS standard_price', false)
            ->select('COALESCE(c.is_active, 1) AS is_active', false)
            ->from('mst_purchase_catalog c')
            ->where_in('c.profile_key', $profileKeys)
            ->order_by('COALESCE(c.is_active, 1)', 'DESC', false)
            ->order_by('COALESCE(c.last_purchase_date, \'1000-01-01\')', 'DESC', false)
            ->order_by('c.id', 'DESC');

        $result = [];
        foreach ($query->get()->result_array() as $row) {
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            if ($profileKey === '' || isset($result[$profileKey])) {
                continue;
            }
            $lastPrice = max(0, round((float)($row['last_unit_price'] ?? 0), 2));
            $standardPrice = max(0, round((float)($row['standard_price'] ?? 0), 2));
            $unitPrice = $lastPrice > 0 ? $lastPrice : $standardPrice;
            $factor = max(0.000001, (float)($row['content_per_buy'] ?? 1));
            $row['avg_cost_per_content'] = round($unitPrice / $factor, 6);
            $row['price_source'] = $lastPrice > 0
                ? 'Harga beli terakhir katalog'
                : ($standardPrice > 0 ? 'Harga standar katalog' : '');
            $result[$profileKey] = $row;
        }

        return $result;
    }

    /**
     * Reads the active monthly balance again immediately before an adjustment is
     * created. The browser snapshot is only a display value; it must not become
     * the accounting source when another movement has happened in the meantime.
     */
    private function current_material_recon_snapshot(
        string $opnameDate,
        int $divisionId,
        string $destinationType,
        string $identityKey,
        int $contentUomId
    ): array {
        $destinationType = strtoupper(trim($destinationType));
        if ($destinationType === '' || in_array($destinationType, ['ALL', 'REGULER', 'EVENT'], true)) {
            return [
                'ok' => false,
                'message' => 'Tujuan stok harus mengikuti baris yang dipilih, bukan filter Event/Reguler. Muat ulang Daily Recon lalu coba kembali.',
            ];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)
            || $divisionId <= 0
            || $identityKey === ''
            || $contentUomId <= 0
        ) {
            return ['ok' => false, 'message' => 'Konteks stok bahan baku tidak lengkap. Muat ulang Daily Recon lalu coba kembali.'];
        }
        if (!$this->db->table_exists('inv_division_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel saldo bulanan bahan baku belum tersedia.'];
        }

        $monthKey = date('Y-m-01', strtotime($opnameDate));
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $query = $this->db
                ->select('id, item_id, material_id, buy_uom_id, content_uom_id, identity_key, profile_key')
                ->select('profile_name, profile_brand, profile_description, profile_expired_date')
                ->select('profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code')
                ->select('closing_qty_content, avg_cost_per_content')
                ->from('inv_division_monthly_stock')
                ->where('month_key', $monthKey)
                ->where('division_id', $divisionId)
                ->where('destination_type', $destinationType)
                ->where('identity_key', $identityKey)
                ->where('content_uom_id', $contentUomId)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get();
            $row = $query ? $query->row_array() : [];
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (empty($row)) {
            $deficit = $this->find_open_material_deficit_recon_profile(
                $divisionId,
                $destinationType,
                $identityKey,
                $contentUomId
            );
            if ($deficit !== null) {
                return [
                    'ok' => true,
                    'monthly_stock_id' => 0,
                    'system_qty_content' => 0.0,
                    'avg_cost_per_content' => round((float)($deficit['avg_cost_per_content'] ?? 0), 6),
                    'item_id' => (int)($deficit['item_id'] ?? 0),
                    'material_id' => (int)($deficit['material_id'] ?? 0),
                    'buy_uom_id' => (int)($deficit['buy_uom_id'] ?? 0),
                    'content_uom_id' => (int)($deficit['content_uom_id'] ?? 0),
                    'identity_key' => $identityKey,
                    'profile_key' => $identityKey,
                    'profile_name' => (string)($deficit['profile_name'] ?? ''),
                    'profile_brand' => (string)($deficit['profile_brand'] ?? ''),
                    'profile_description' => (string)($deficit['profile_description'] ?? ''),
                    'profile_expired_date' => null,
                    'profile_content_per_buy' => (float)($deficit['profile_content_per_buy'] ?? 1),
                    'profile_buy_uom_code' => (string)($deficit['profile_buy_uom_code'] ?? ''),
                    'profile_content_uom_code' => (string)($deficit['profile_content_uom_code'] ?? ''),
                    'is_deficit_virtual' => true,
                    'cost_reference_source' => (string)($deficit['cost_reference_source'] ?? ''),
                ];
            }
            return [
                'ok' => false,
                'message' => 'Profil stok tidak ditemukan pada saldo bulan aktif atau daftar defisit terbuka. Muat ulang Daily Recon lalu pilih profil yang tepat.',
            ];
        }

        $profileKey = trim((string)($row['profile_key'] ?? ''));
        $catalog = $this->load_material_catalog_profiles([$profileKey ?: $identityKey])[$profileKey ?: $identityKey] ?? [];
        $avgCost = round((float)($row['avg_cost_per_content'] ?? 0), 6);
        $costReference = 'Rata-rata stok aktif';
        if ($avgCost <= 0.000001 && (float)($catalog['avg_cost_per_content'] ?? 0) > 0) {
            $avgCost = round((float)$catalog['avg_cost_per_content'], 6);
            $costReference = (string)($catalog['price_source'] ?? 'Harga katalog');
        }

        return [
            'ok' => true,
            'monthly_stock_id' => (int)$row['id'],
            'system_qty_content' => round((float)($row['closing_qty_content'] ?? 0), 4),
            'avg_cost_per_content' => $avgCost,
            'item_id' => (int)($row['item_id'] ?? 0),
            'material_id' => (int)($row['material_id'] ?? 0),
            'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
            'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
            'identity_key' => (string)($row['identity_key'] ?? $identityKey),
            'profile_key' => $profileKey,
            'profile_name' => (string)($row['profile_name'] ?? ''),
            'profile_brand' => (string)($row['profile_brand'] ?? ''),
            'profile_description' => (string)($row['profile_description'] ?? ''),
            'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
            'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 1),
            'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
            'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
            'cost_reference_source' => $costReference,
        ];
    }

    /**
     * The browser may only post the exact profile that was re-read from the
     * server. This matters most for deficit rows where similarly named items
     * can otherwise be confused in a division.
     */
    private function validate_material_recon_payload_identity(array $payload, array $snapshot): ?string
    {
        foreach (['item_id', 'material_id', 'buy_uom_id', 'content_uom_id'] as $field) {
            $expected = (int)($snapshot[$field] ?? 0);
            if ($expected <= 0) {
                continue;
            }
            if ((int)($payload[$field] ?? 0) !== $expected) {
                return 'Profil Daily Recon sudah berubah atau tidak cocok dengan data yang dipilih. Muat ulang halaman lalu pilih profil yang tepat.';
            }
        }

        $expectedProfile = trim((string)($snapshot['profile_key'] ?? ''));
        if ($expectedProfile !== '' && trim((string)($payload['profile_key'] ?? '')) !== $expectedProfile) {
            return 'Profile pembelian yang dikirim tidak sesuai dengan profil stok yang diverifikasi. Muat ulang Daily Recon lalu coba lagi.';
        }

        return null;
    }

    /**
     * Only server-verified profile values are written by Daily Recon. This
     * avoids mixing a similarly named catalog profile into an adjustment.
     */
    private function material_recon_snapshot_profile_values(array $snapshot, string $identityKey): array
    {
        return [
            'item_id' => (int)($snapshot['item_id'] ?? 0) ?: null,
            'material_id' => (int)($snapshot['material_id'] ?? 0) ?: null,
            'buy_uom_id' => (int)($snapshot['buy_uom_id'] ?? 0) ?: null,
            'content_uom_id' => (int)($snapshot['content_uom_id'] ?? 0),
            'profile_key' => $this->ns($snapshot['profile_key'] ?? $identityKey),
            'profile_name' => $this->ns($snapshot['profile_name'] ?? null),
            'profile_brand' => $this->ns($snapshot['profile_brand'] ?? null),
            'profile_description' => $this->ns($snapshot['profile_description'] ?? null),
            'profile_expired_date' => $this->nd((string)($snapshot['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => max(0.000001, (float)($snapshot['profile_content_per_buy'] ?? 1)),
            'profile_buy_uom_code' => $this->ns($snapshot['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->ns($snapshot['profile_content_uom_code'] ?? null),
        ];
    }

    private function division_opname_profile_key(array $row): string
    {
        return implode('|', [
            (int)($row['division_id'] ?? 0),
            strtoupper((string)($row['destination_type'] ?? 'OTHER')),
            (string)($row['identity_key'] ?? ($row['profile_key'] ?? '')),
        ]);
    }

    private function material_lot_count_key(array $row): string
    {
        return implode('|', [
            (int)($row['division_id'] ?? 0),
            strtoupper((string)($row['destination_type'] ?? 'OTHER')),
            (int)($row['item_id'] ?? 0),
            (int)($row['material_id'] ?? 0),
            (int)($row['buy_uom_id'] ?? 0),
            (int)($row['content_uom_id'] ?? 0),
            (string)($row['profile_key'] ?? ''),
        ]);
    }

    private function daily_recon_confirm_mode(): string
    {
        $mode = strtoupper(trim($this->daily_recon_config_value('pos.daily_recon_confirm_mode', 'BULK_ALLOWED')));
        return in_array($mode, ['BULK_ALLOWED', 'ROW_REQUIRED'], true) ? $mode : 'BULK_ALLOWED';
    }

    private function daily_recon_config_value(string $key, string $default = ''): string
    {
        if ($key === '' || !$this->db->table_exists('sys_app_config')) {
            return $default;
        }
        $row = $this->db->select('config_value')
            ->from('sys_app_config')
            ->where('config_key', $key)
            ->limit(1)
            ->get()
            ->row_array();
        return $row ? (string)($row['config_value'] ?? $default) : $default;
    }

    private function daily_recon_required_tokens(string $configKey): array
    {
        $raw = $this->daily_recon_config_value($configKey, '');
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = strtoupper(trim((string)$part));
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }
        return $tokens;
    }

    private function daily_recon_token_matches(array $tokens, array $candidates): bool
    {
        if (empty($tokens)) {
            return false;
        }
        foreach ($candidates as $candidate) {
            $value = strtoupper(trim((string)$candidate));
            if ($value !== '' && isset($tokens[$value])) {
                return true;
            }
        }
        return false;
    }

    private function jsonOk(array $data = [], string $message = ''): void
    {
        $this->discard_json_response_noise();
        $payload = ['ok' => true];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        $payload['data'] = $data;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function jsonError(string $message, int $status = 400): void
    {
        $this->discard_json_response_noise();
        $this->output->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * CI has already loaded the authenticated user before an action reaches
     * this point. Long stock writers must not keep the browser's file-session
     * lock, otherwise unrelated AJAX polling can time out and return HTML.
     */
    private function release_request_session_lock(): void
    {
        if (PHP_SAPI !== 'cli' && function_exists('session_status')
            && session_status() === PHP_SESSION_ACTIVE
            && function_exists('session_write_close')) {
            session_write_close();
        }
    }

    /**
     * Daily Recon is called through fetch(), so a PHP notice or an incidental
     * HTML fragment must not turn an already-handled result into invalid JSON.
     * Keep the fragment in the server log and send one clean JSON response.
     */
    private function begin_json_response(): void
    {
        if ($this->json_response_buffer_started || headers_sent()) {
            return;
        }
        ob_start();
        $this->json_response_buffer_started = true;
    }

    private function discard_json_response_noise(): void
    {
        if (!$this->json_response_buffer_started) {
            return;
        }
        $this->json_response_buffer_started = false;
        $noise = ob_get_level() > 0 ? (string)ob_get_clean() : '';
        if (trim($noise) !== '') {
            log_message('error', 'daily recon suppressed unexpected response output: ' . substr(trim($noise), 0, 1000));
        }
    }

    private function request_payload(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        $post = $this->input->post(null, false);
        return is_array($post) ? $post : [];
    }

    private function ns($value): ?string
    {
        $v = trim((string)($value ?? ''));
        return $v === '' ? null : $v;
    }

    public function opname_monthly()
    {
        parent::stock_division_opname_monthly();
    }

    private function nd(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        $d = date_create_from_format('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }
}
