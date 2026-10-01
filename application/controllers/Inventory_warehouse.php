<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory_warehouse extends Purchase
{
    /**
     * Fast physical-count writer used by Defisit Stok and Stock Health.
     * It deliberately delegates the actual stock/lot write to the existing
     * warehouse adjustment writer so there is no second inventory pathway.
     */
    private $warehouse_recon_json_buffer_started = false;

    public function index()
    {
        parent::stock_warehouse_index();
    }

    public function opening()
    {
        parent::stock_opening_warehouse_index();
    }

    public function stok_awal()
    {
        parent::stock_opening_warehouse_generated();
    }

    public function adjustment()
    {
        parent::stock_adjustment_warehouse_index();
    }

    public function daily()
    {
        parent::stock_warehouse_daily_index();
    }

    public function movement()
    {
        parent::stock_warehouse_movement_index();
    }

    public function daily_matrix()
    {
        parent::stock_warehouse_daily_matrix();
    }

    public function matrix_view()
    {
        parent::inventory_warehouse_daily_index();
    }

    public function lot()
    {
        parent::warehouse_lot_audit_index();
    }

    /**
     * Physical count entry point for an exact warehouse profile selected from
     * Stock Health. The actual write still goes through recon_quick_adjust().
     */
    public function recon()
    {
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'create');
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'edit');

        $reconDate = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reconDate)) {
            $reconDate = date('Y-m-d');
        }
        $context = [
            'item_id' => (int)$this->input->get('item_id', true),
            'material_id' => (int)$this->input->get('material_id', true),
            'buy_uom_id' => (int)$this->input->get('buy_uom_id', true),
            'content_uom_id' => (int)$this->input->get('content_uom_id', true),
            'profile_key' => trim((string)$this->input->get('profile_key', true)),
        ];
        $hasProfile = $context['content_uom_id'] > 0
            && ($context['item_id'] > 0 || $context['material_id'] > 0);
        $snapshot = $hasProfile
            ? $this->warehouse_recon_snapshot($reconDate, $context)
            : ['ok' => false, 'message' => 'Pilih barang dari Stock Health atau Defisit Stok terlebih dahulu.'];

        if (!empty($snapshot['ok'])) {
            $snapshot['inventory_name'] = $this->warehouse_recon_inventory_name($snapshot);
        }

        $this->render('inventory/stock_warehouse_recon_index', [
            'page_title' => 'Recon Stok Fisik Gudang',
            'active_menu' => 'inventory.stock.health',
            'recon_date' => $reconDate,
            'context' => $context,
            'snapshot' => $snapshot,
            'health_url' => site_url('inventory/stock/health') . '?' . http_build_query([
                'month' => substr($reconDate, 0, 7),
            ]),
            'deficit_url' => site_url('inventory/stock/deficits') . '?' . http_build_query([
                'stock_domain' => 'MATERIAL',
                'location_scope' => 'WAREHOUSE',
                'q' => (string)($snapshot['inventory_name'] ?? ''),
            ]),
        ]);
    }

    public function recon_quick_adjust()
    {
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'create');
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'edit');
        $this->warehouse_recon_begin_json_response();

        $payload = $this->warehouse_recon_request_payload();
        $reconDate = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reconDate)) {
            $this->warehouse_recon_json_error('Tanggal hitung fisik tidak valid.', 422);
            return;
        }

        $physicalProvided = array_key_exists('physical_qty_content', $payload)
            && $payload['physical_qty_content'] !== '';
        if (!$physicalProvided || !is_numeric($payload['physical_qty_content'])) {
            $this->warehouse_recon_json_error('Masukkan stok fisik gudang terlebih dahulu.', 422);
            return;
        }
        $physicalQty = round((float)$payload['physical_qty_content'], 4);
        if ($physicalQty < -0.0001) {
            $this->warehouse_recon_json_error('Stok fisik tidak boleh bernilai negatif.', 422);
            return;
        }

        // The authenticated user has been loaded already. Release the file
        // session lock before the FIFO and ledger transaction starts.
        $this->warehouse_recon_release_session_lock();
        $snapshot = $this->warehouse_recon_snapshot($reconDate, $payload);
        if (!($snapshot['ok'] ?? false)) {
            $this->warehouse_recon_json_error((string)($snapshot['message'] ?? 'Profil stok gudang tidak dapat diverifikasi.'), 409);
            return;
        }

        $identityError = $this->warehouse_recon_validate_identity($payload, $snapshot);
        if ($identityError !== null) {
            $this->warehouse_recon_json_error($identityError, 409);
            return;
        }

        $systemQty = round((float)($snapshot['system_qty_content'] ?? 0), 4);
        $deltaQty = round($physicalQty - $systemQty, 4);
        $settleOpenDeficit = !empty($payload['settle_open_deficit']);
        $notes = trim((string)($payload['notes'] ?? ''));
        $userId = (int)($this->current_user['id'] ?? 0);

        // A physical count can still need work when the monthly stock is
        // already correct but the OPEN FIFO lots are not. In that case do not
        // fabricate an adjustment movement; reconcile only the lot structure
        // and leave the authoritative monthly balance unchanged.
        if (abs($deltaQty) <= 0.0001) {
            $lotRecon = $this->warehouse_recon_sync_lots_to_physical(
                $snapshot,
                $reconDate,
                $physicalQty,
                $notes,
                $userId
            );
            if (!($lotRecon['ok'] ?? false)) {
                $this->warehouse_recon_json_error((string)($lotRecon['message'] ?? 'Gagal menyamakan lot gudang dengan hasil hitung fisik.'), 422);
                return;
            }

            $settledQty = 0.0;
            $warning = '';
            if ($settleOpenDeficit) {
                if ($physicalQty <= 0.0001) {
                    $warning = 'Stok fisik nol, sehingga defisit tidak dapat ditutup dari angka nol.';
                } else {
                    $settlement = $this->warehouse_recon_settle_deficit($snapshot, $reconDate, $physicalQty, $notes, $userId, (int)($payload['deficit_id'] ?? 0));
                    if ($settlement['ok'] ?? false) {
                        $settledQty = round((float)($settlement['settled_qty'] ?? 0), 4);
                    } else {
                        $warning = (string)($settlement['message'] ?? 'Defisit belum dapat ditutup karena tidak ditemukan identitas defisit yang sama.');
                    }
                }
            }

            $lotAction = strtoupper((string)($lotRecon['data']['action'] ?? 'NONE'));
            $message = $lotAction === 'NONE'
                ? 'Stok sistem dan lot aktif sudah sama dengan hasil hitung fisik. Tidak ada koreksi baru yang perlu diposting.'
                : 'Jumlah stok sistem tidak berubah. Lot aktif berhasil disamakan dengan hasil hitung fisik.';
            if ($settledQty > 0.0001) {
                $message .= ' Defisit yang profilnya sama juga telah ditutup.';
            }
            $this->warehouse_recon_json_ok([
                'lot_reconciliation' => $lotRecon['data'] ?? [],
                'settled_qty' => $settledQty,
                'warning' => $warning,
            ], $message);
            return;
        }

        $unitCost = round((float)($snapshot['avg_cost_per_content'] ?? 0), 6);
        if ($deltaQty > 0.0001 && $unitCost <= 0.000001) {
            $this->warehouse_recon_json_error('Adjustment plus dari stok fisik membutuhkan harga katalog atau rata-rata biaya yang valid. Periksa profil pembelian barang ini terlebih dahulu.', 422);
            return;
        }

        $line = [
            'input_mode' => 'PHYSICAL_COUNT',
            'item_id' => (int)($snapshot['item_id'] ?? 0) ?: null,
            'material_id' => (int)($snapshot['material_id'] ?? 0) ?: null,
            'buy_uom_id' => (int)($snapshot['buy_uom_id'] ?? 0) ?: null,
            'content_uom_id' => (int)($snapshot['content_uom_id'] ?? 0),
            'profile_key' => $this->warehouse_recon_nullable_string($snapshot['profile_key'] ?? null),
            'profile_name' => $this->warehouse_recon_nullable_string($snapshot['profile_name'] ?? null),
            'profile_brand' => $this->warehouse_recon_nullable_string($snapshot['profile_brand'] ?? null),
            'profile_description' => $this->warehouse_recon_nullable_string($snapshot['profile_description'] ?? null),
            'profile_expired_date' => $this->warehouse_recon_normalize_date((string)($snapshot['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => max(0.000001, (float)($snapshot['profile_content_per_buy'] ?? 1)),
            'profile_buy_uom_code' => $this->warehouse_recon_nullable_string($snapshot['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->warehouse_recon_nullable_string($snapshot['profile_content_uom_code'] ?? null),
            'system_qty_snapshot_content' => $systemQty,
            'physical_qty_snapshot_content' => $physicalQty,
            'settle_open_deficit' => $settleOpenDeficit ? 1 : 0,
            'settle_open_deficit_qty_content' => $settleOpenDeficit ? max(0, $physicalQty) : 0,
            'unit_cost' => $unitCost,
            'note' => $notes,
        ];
        if ($deltaQty > 0.0001) {
            $line['qty_adjustment_plus_content'] = abs($deltaQty);
            $line['adjustment_plus_reason_code'] = 'physical_count';
        } else {
            $line['qty_variance_content'] = abs($deltaQty);
            $line['variance_reason_code'] = 'physical_count';
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $draft = $this->Purchase_model->save_stock_adjustment([
                'id' => 0,
                'adjustment_date' => $reconDate,
                'stock_scope' => 'WAREHOUSE',
                'notes' => 'Recon stok fisik gudang' . ($notes !== '' ? ': ' . $notes : ''),
            ], [$line], $userId);
            if (!($draft['ok'] ?? false)) {
                $this->warehouse_recon_json_error((string)($draft['message'] ?? 'Gagal menyimpan adjustment recon gudang.'), 422);
                return;
            }

            $adjustmentId = (int)($draft['id'] ?? 0);
            $posted = $this->Purchase_model->post_stock_adjustment($adjustmentId, $userId, (string)$this->input->ip_address());
            if (!($posted['ok'] ?? false)) {
                if ($adjustmentId > 0) {
                    $this->Purchase_model->delete_draft_stock_adjustment($adjustmentId);
                }
                $this->warehouse_recon_json_error((string)($posted['message'] ?? 'Gagal memposting recon gudang.'), 422);
                return;
            }
        } catch (Throwable $e) {
            log_message('error', 'warehouse physical recon failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->warehouse_recon_json_error('Recon gudang gagal diproses di server. Periksa log server untuk rincian teknis.', 500);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        $settledQty = 0.0;
        $warning = '';
        // Older deployments may not yet have the settlement columns. The
        // adjustment remains valid; settle the deficit afterward and surface a
        // warning rather than returning a false failure after stock changed.
        if ($settleOpenDeficit && !$this->db->field_exists('settle_open_deficit', 'inv_stock_adjustment_line')) {
            $settlement = $this->warehouse_recon_settle_deficit($snapshot, $reconDate, $physicalQty, $notes, $userId, (int)($payload['deficit_id'] ?? 0));
            if ($settlement['ok'] ?? false) {
                $settledQty = round((float)($settlement['settled_qty'] ?? 0), 4);
            } else {
                $warning = 'Adjustment sudah diposting, tetapi defisit belum tertutup: ' . (string)($settlement['message'] ?? 'periksa modul Defisit Stok.');
            }
        }

        $this->warehouse_recon_json_ok([
            'adjustment_id' => $adjustmentId,
            'settled_qty' => $settledQty,
            'warning' => $warning,
        ], 'Recon gudang berhasil diposting. Stok sistem dan lot aktif telah mengikuti hasil hitung fisik.' . ($settledQty > 0 ? ' Defisit yang cocok juga telah ditutup.' : ''));
    }

    /**
     * Reads the active warehouse row again on the server. Browser values are
     * display-only and never become the authority for the adjustment writer.
     */
    private function warehouse_recon_snapshot(string $reconDate, array $payload): array
    {
        $itemId = (int)($payload['item_id'] ?? 0);
        $materialId = (int)($payload['material_id'] ?? 0);
        $buyUomId = (int)($payload['buy_uom_id'] ?? 0);
        $contentUomId = (int)($payload['content_uom_id'] ?? 0);
        $profileKey = trim((string)($payload['profile_key'] ?? $payload['identity_key'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reconDate)
            || $contentUomId <= 0 || ($itemId <= 0 && $materialId <= 0)
        ) {
            return ['ok' => false, 'message' => 'Konteks profil gudang tidak lengkap. Muat ulang halaman lalu pilih barang yang tepat.'];
        }
        if (!$this->db->table_exists('inv_warehouse_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel saldo gudang belum tersedia.'];
        }

        $month = date('Y-m-01', strtotime($reconDate));
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $query = $this->db
                ->select('id, item_id, material_id, buy_uom_id, content_uom_id, identity_key, profile_key')
                ->select('profile_name, profile_brand, profile_description, profile_expired_date')
                ->select('profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code')
                ->select('closing_qty_content, avg_cost_per_content')
                ->from('inv_warehouse_monthly_stock')
                ->where('month_key', $month)
                ->where('content_uom_id', $contentUomId)
                ->where('item_id <=> ' . $this->db->escape($itemId > 0 ? $itemId : null), null, false)
                ->where('material_id <=> ' . $this->db->escape($materialId > 0 ? $materialId : null), null, false)
                ->where('buy_uom_id <=> ' . $this->db->escape($buyUomId > 0 ? $buyUomId : null), null, false)
                ->where("COALESCE(profile_key, '') = " . $this->db->escape($profileKey), null, false)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get();
            $row = $query ? $query->row_array() : [];
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (empty($row)) {
            $deficit = $this->db
                ->select('*')
                ->from('inv_stock_deficit')
                ->where('stock_domain', 'MATERIAL')
                ->where('location_scope', 'WAREHOUSE')
                ->where('status', 'OPEN')
                ->where('content_uom_id', $contentUomId)
                ->where('item_id <=> ' . $this->db->escape($itemId > 0 ? $itemId : null), null, false)
                ->where('material_id <=> ' . $this->db->escape($materialId > 0 ? $materialId : null), null, false)
                ->where('buy_uom_id <=> ' . $this->db->escape($buyUomId > 0 ? $buyUomId : null), null, false)
                ->where('profile_key <=> ' . $this->db->escape($profileKey !== '' ? $profileKey : null), null, false)
                ->order_by('deficit_date', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            if (empty($deficit)) {
                return ['ok' => false, 'message' => 'Profil tidak ditemukan pada stok gudang bulan aktif atau defisit terbuka. Muat ulang halaman lalu coba kembali.'];
            }

            $catalog = [];
            if ($profileKey !== '' && $this->db->table_exists('mst_purchase_catalog')) {
                $catalog = $this->db->select('catalog_name, brand_name, line_description, content_per_buy, standard_price, last_unit_price')
                    ->from('mst_purchase_catalog')
                    ->where('profile_key', $profileKey)
                    ->order_by('is_active', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array() ?: [];
            }
            $contentPerBuy = max(0.000001, (float)($catalog['content_per_buy'] ?? 1));
            $catalogPrice = max(0, (float)($catalog['last_unit_price'] ?? $catalog['standard_price'] ?? 0));
            $catalogCost = $catalogPrice > 0 ? round($catalogPrice / $contentPerBuy, 6) : 0.0;
            return [
                'ok' => true,
                'monthly_stock_id' => 0,
                'system_qty_content' => 0.0,
                'lot_qty_content' => 0.0,
                'lot_value' => 0.0,
                'lot_count' => 0,
                'avg_cost_per_content' => round(max((float)($deficit['estimated_unit_cost'] ?? 0), $catalogCost), 6),
                'item_id' => (int)($deficit['item_id'] ?? 0),
                'material_id' => (int)($deficit['material_id'] ?? 0),
                'buy_uom_id' => (int)($deficit['buy_uom_id'] ?? 0),
                'content_uom_id' => (int)($deficit['content_uom_id'] ?? 0),
                'profile_key' => $profileKey !== '' ? $profileKey : null,
                'profile_name' => (string)($catalog['catalog_name'] ?? ''),
                'profile_brand' => (string)($catalog['brand_name'] ?? ''),
                'profile_description' => (string)($catalog['line_description'] ?? ''),
                'profile_expired_date' => null,
                'profile_content_per_buy' => $contentPerBuy,
                'profile_buy_uom_code' => '',
                'profile_content_uom_code' => '',
                'is_deficit_virtual' => true,
            ];
        }

        $lotSummary = $this->warehouse_recon_lot_summary($row);
        return [
            'ok' => true,
            'monthly_stock_id' => (int)$row['id'],
            'system_qty_content' => round((float)($row['closing_qty_content'] ?? 0), 4),
            'lot_qty_content' => round((float)($lotSummary['lot_qty_content'] ?? 0), 4),
            'lot_value' => round((float)($lotSummary['lot_value'] ?? 0), 2),
            'lot_count' => (int)($lotSummary['lot_count'] ?? 0),
            'avg_cost_per_content' => round((float)($row['avg_cost_per_content'] ?? 0), 6),
            'item_id' => (int)($row['item_id'] ?? 0),
            'material_id' => (int)($row['material_id'] ?? 0),
            'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
            'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
            'profile_key' => $this->warehouse_recon_nullable_string($row['profile_key'] ?? null),
            'profile_name' => (string)($row['profile_name'] ?? ''),
            'profile_brand' => (string)($row['profile_brand'] ?? ''),
            'profile_description' => (string)($row['profile_description'] ?? ''),
            'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
            'profile_content_per_buy' => max(0.000001, (float)($row['profile_content_per_buy'] ?? 1)),
            'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
            'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
        ];
    }

    /**
     * The physical recon screen needs the live lot total as well as the
     * authoritative monthly balance. This keeps the operator from assuming
     * that an unchanged stock quantity also means the FIFO lots are aligned.
     */
    private function warehouse_recon_lot_summary(array $identity): array
    {
        if (!$this->db->table_exists('inv_material_fifo_lot')) {
            return ['lot_qty_content' => 0.0, 'lot_value' => 0.0, 'lot_count' => 0];
        }

        $itemId = (int)($identity['item_id'] ?? 0);
        $materialId = (int)($identity['material_id'] ?? 0);
        $buyUomId = (int)($identity['buy_uom_id'] ?? 0);
        $contentUomId = (int)($identity['content_uom_id'] ?? 0);
        $profileKey = trim((string)($identity['profile_key'] ?? ''));

        $query = $this->db
            ->select('COALESCE(SUM(l.qty_balance), 0) AS lot_qty_content', false)
            ->select('COALESCE(SUM(l.qty_balance * l.unit_cost), 0) AS lot_value', false)
            ->select('COUNT(CASE WHEN l.qty_balance > 0.0001 THEN 1 END) AS lot_count', false)
            ->from('inv_material_fifo_lot l')
            ->where('l.location_scope', 'WAREHOUSE')
            ->where('l.status', 'OPEN')
            ->where('l.content_uom_id', $contentUomId)
            ->where('l.item_id <=> ' . $this->db->escape($itemId > 0 ? $itemId : null), null, false)
            ->where('l.material_id <=> ' . $this->db->escape($materialId > 0 ? $materialId : null), null, false)
            ->where('l.buy_uom_id <=> ' . $this->db->escape($buyUomId > 0 ? $buyUomId : null), null, false)
            ->where("COALESCE(l.profile_key, '') = " . $this->db->escape($profileKey), null, false)
            ->get();
        $row = $query ? $query->row_array() : [];

        return [
            'lot_qty_content' => round((float)($row['lot_qty_content'] ?? 0), 4),
            'lot_value' => round((float)($row['lot_value'] ?? 0), 2),
            'lot_count' => (int)($row['lot_count'] ?? 0),
        ];
    }

    private function warehouse_recon_inventory_name(array $snapshot): string
    {
        $itemId = (int)($snapshot['item_id'] ?? 0);
        $materialId = (int)($snapshot['material_id'] ?? 0);
        if ($itemId > 0 && $this->db->table_exists('mst_item')) {
            $row = $this->db->select('item_name')->where('id', $itemId)->get('mst_item')->row_array();
            if (!empty($row['item_name'])) {
                return (string)$row['item_name'];
            }
        }
        if ($materialId > 0 && $this->db->table_exists('mst_material')) {
            $row = $this->db->select('material_name')->where('id', $materialId)->get('mst_material')->row_array();
            if (!empty($row['material_name'])) {
                return (string)$row['material_name'];
            }
        }
        return trim((string)($snapshot['profile_name'] ?? '')) ?: 'Profil bahan baku';
    }

    private function warehouse_recon_validate_identity(array $payload, array $snapshot): ?string
    {
        foreach (['item_id', 'material_id', 'buy_uom_id', 'content_uom_id'] as $field) {
            $expected = (int)($snapshot[$field] ?? 0);
            if ($expected > 0 && (int)($payload[$field] ?? 0) !== $expected) {
                return 'Profil gudang sudah berubah atau tidak cocok dengan data yang dipilih. Muat ulang halaman lalu coba kembali.';
            }
        }
        $expectedProfile = trim((string)($snapshot['profile_key'] ?? ''));
        $receivedProfile = trim((string)($payload['profile_key'] ?? $payload['identity_key'] ?? ''));
        if ($expectedProfile !== '' && $expectedProfile !== $receivedProfile) {
            return 'Profil pembelian yang dikirim tidak sesuai dengan saldo gudang yang diverifikasi. Muat ulang halaman lalu coba kembali.';
        }
        return null;
    }

    /**
     * A lot-only physical recon is needed when monthly stock is already equal
     * to the count, but stale OPEN lots still carry a different balance.
     */
    private function warehouse_recon_sync_lots_to_physical(
        array $snapshot,
        string $reconDate,
        float $physicalQty,
        string $notes,
        int $userId
    ): array {
        if (!file_exists(APPPATH . 'libraries/MaterialFifoManager.php')) {
            return ['ok' => false, 'message' => 'Service lot material belum tersedia.'];
        }

        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->load->library('InventoryPeriodGuard');
            $period = $this->inventoryperiodguard->ensureActiveMonthOpen(
                'MATERIAL',
                $reconDate,
                $userId > 0 ? $userId : null,
                'Automatic material period from warehouse physical recon'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        $this->load->library('MaterialFifoManager');
        $ready = $this->materialfifomanager->ensureReady();
        if (!($ready['ok'] ?? false)) {
            return $ready;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $result = $this->materialfifomanager->reconcileLotsToAuthoritativeBalance([
                'location_scope' => 'WAREHOUSE',
                'destination_type' => 'GUDANG',
                'event_date' => $reconDate,
                'item_id' => !empty($snapshot['item_id']) ? (int)$snapshot['item_id'] : null,
                'material_id' => !empty($snapshot['material_id']) ? (int)$snapshot['material_id'] : null,
                'buy_uom_id' => !empty($snapshot['buy_uom_id']) ? (int)$snapshot['buy_uom_id'] : null,
                'content_uom_id' => !empty($snapshot['content_uom_id']) ? (int)$snapshot['content_uom_id'] : null,
                'profile_key' => $this->warehouse_recon_nullable_string($snapshot['profile_key'] ?? null),
                'profile_expired_date' => $this->warehouse_recon_normalize_date((string)($snapshot['profile_expired_date'] ?? '')),
                'target_qty_content' => max(0, $physicalQty),
                'unit_cost' => round((float)($snapshot['avg_cost_per_content'] ?? 0), 6),
                'source_table' => 'inventory_warehouse_physical_recon',
                'source_id' => !empty($snapshot['monthly_stock_id']) ? (int)$snapshot['monthly_stock_id'] : null,
                'notes' => $notes,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
            if (!($result['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $result;
            }
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal menyimpan koreksi lot hasil hitung fisik gudang.'];
            }
            $this->db->trans_commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'warehouse lot-only physical recon failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return ['ok' => false, 'message' => 'Koreksi lot gudang gagal diproses di server. Periksa log server untuk rincian teknis.'];
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    private function warehouse_recon_settle_deficit(array $snapshot, string $date, float $physicalQty, string $notes, int $userId, int $deficitId): array
    {
        if (!file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            return ['ok' => false, 'message' => 'Service defisit stok belum tersedia.'];
        }
        $this->load->library('InventoryDeficitService');
        if (!$this->inventorydeficitservice->isReady()) {
            return ['ok' => false, 'message' => 'Fondasi defisit stok belum siap. Jalankan SQL inventory terbaru terlebih dahulu.'];
        }
        return $this->inventorydeficitservice->settle([
            'stock_domain' => 'MATERIAL',
            'deficit_date' => $date,
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'item_id' => !empty($snapshot['item_id']) ? (int)$snapshot['item_id'] : null,
            'material_id' => !empty($snapshot['material_id']) ? (int)$snapshot['material_id'] : null,
            'buy_uom_id' => !empty($snapshot['buy_uom_id']) ? (int)$snapshot['buy_uom_id'] : null,
            'content_uom_id' => !empty($snapshot['content_uom_id']) ? (int)$snapshot['content_uom_id'] : null,
            'profile_key' => $this->warehouse_recon_nullable_string($snapshot['profile_key'] ?? null),
            'qty_available' => max(0, $physicalQty),
            'estimated_unit_cost' => round((float)($snapshot['avg_cost_per_content'] ?? 0), 6),
            'source_module' => 'INVENTORY_RECON',
            'source_table' => 'inventory_deficit_recon',
            'source_id' => $deficitId > 0 ? $deficitId : null,
            'notes' => 'Recon gudang mengonfirmasi penyelesaian defisit.' . ($notes !== '' ? ' ' . $notes : ''),
            'created_by' => $userId > 0 ? $userId : null,
        ]);
    }

    private function warehouse_recon_begin_json_response(): void
    {
        if ($this->warehouse_recon_json_buffer_started || headers_sent()) {
            return;
        }
        ob_start();
        $this->warehouse_recon_json_buffer_started = true;
    }

    private function warehouse_recon_discard_json_noise(): void
    {
        if (!$this->warehouse_recon_json_buffer_started) {
            return;
        }
        $this->warehouse_recon_json_buffer_started = false;
        $noise = ob_get_level() > 0 ? (string)ob_get_clean() : '';
        if (trim($noise) !== '') {
            log_message('error', 'warehouse recon suppressed unexpected response output: ' . substr(trim($noise), 0, 1000));
        }
    }

    private function warehouse_recon_json_ok(array $data = [], string $message = ''): void
    {
        $this->warehouse_recon_discard_json_noise();
        $payload = ['ok' => true, 'data' => $data];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        $this->output->set_content_type('application/json')->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function warehouse_recon_json_error(string $message, int $status = 400): void
    {
        $this->warehouse_recon_discard_json_noise();
        $this->output->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function warehouse_recon_request_payload(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $post = $this->input->post(null, false);
        return is_array($post) ? $post : [];
    }

    private function warehouse_recon_release_session_lock(): void
    {
        if (PHP_SAPI !== 'cli' && function_exists('session_status')
            && session_status() === PHP_SESSION_ACTIVE && function_exists('session_write_close')) {
            session_write_close();
        }
    }

    private function warehouse_recon_nullable_string($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function warehouse_recon_normalize_date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public function opname_monthly()
    {
        parent::stock_warehouse_opname_monthly();
    }
}
