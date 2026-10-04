<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_mobile_offline_import_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_model');
    }

    public function find_bound_event(string $clientEventId, array $binding): ?array
    {
        $row = $this->db->from('pos_mobile_offline_sale_import')
            ->where('client_event_id', $clientEventId)->limit(1)->get()->row_array();
        if (!$row || !$this->same_binding($row, $binding)) {
            return null;
        }
        $response = json_decode((string)($row['response_json'] ?? ''), true);
        return is_array($response) ? $response : [
            'client_event_id' => $clientEventId,
            'posting_status' => (string)$row['import_status'] === 'POSTED' ? 'NEEDS_ACTION' : (string)$row['import_status'],
            'message' => 'Bukti posting Finance belum lengkap. Periksa transaksi sebelum menyinkronkan lagi.',
        ];
    }

    public function import_cash_sale(array $request, array $sale, array $binding, ?array $session): array
    {
        $eventId = (string)$request['client_event_id'];
        $lockName = 'm11:' . substr(hash('sha256', $eventId), 0, 50);
        $lockQuery = $this->db->query('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if (!$lockQuery || (int)($lockQuery->row_array()['acquired'] ?? 0) !== 1) {
            return ['ok' => false, 'http_status' => 503, 'message' => 'Import sedang diproses. Coba sinkronkan lagi dengan event yang sama.'];
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $orderLock = '';
        $paymentLock = '';
        $transactionStarted = false;
        $importRowId = 0;
        try {
            $existing = $this->db->group_start()
                ->where('client_event_id', $eventId)
                ->or_where('local_order_uuid', (string)$request['local_order_uuid'])
                ->or_where('ledger_uuid', (string)$request['ledger_uuid'])
                ->or_where('local_payment_uuid', (string)$sale['local_payment_uuid'])
                ->group_end()
                ->from('pos_mobile_offline_sale_import')->limit(1)->get()->row_array();
            if ($existing) {
                if (
                    (string)$existing['client_event_id'] !== $eventId
                    || (string)$existing['local_order_uuid'] !== (string)$request['local_order_uuid']
                    || (string)$existing['ledger_uuid'] !== (string)$request['ledger_uuid']
                    || (string)$existing['local_payment_uuid'] !== (string)$sale['local_payment_uuid']
                    || !hash_equals((string)$existing['payload_hash'], (string)$request['payload_hash'])
                ) {
                    return ['ok' => false, 'http_status' => 409, 'message' => 'Identitas transaksi lokal sudah dipakai untuk isi transaksi berbeda.'];
                }
                if (!$this->same_binding($existing, $binding)) {
                    return ['ok' => false, 'http_status' => 404, 'message' => 'Transaksi lokal tidak ditemukan pada perangkat ini.'];
                }
                $importRowId = (int)$existing['id'];
                if ((string)$existing['import_status'] === 'POSTED') {
                    $response = json_decode((string)$existing['response_json'], true);
                    if (!is_array($response) || (int)($response['server_order_id'] ?? 0) <= 0 || (int)($response['server_payment_id'] ?? 0) <= 0) {
                        return ['ok' => false, 'http_status' => 503, 'message' => 'Bukti posting Finance belum lengkap. Periksa status import sebelum mencoba lagi.'];
                    }
                    return ['ok' => true, 'http_status' => 200, 'response' => $response + ['duplicate' => true]];
                }
            } else {
                $storedSale = $sale;
                unset($storedSale['terminal_device_key']);
                $inserted = $this->db->insert('pos_mobile_offline_sale_import', [
                    'client_event_id' => $eventId,
                    'local_order_uuid' => (string)$request['local_order_uuid'],
                    'ledger_uuid' => (string)$request['ledger_uuid'],
                    'local_payment_uuid' => (string)$sale['local_payment_uuid'],
                    'payload_hash' => (string)$request['payload_hash'],
                    'actor_user_id' => (int)$binding['user_id'],
                    'actor_employee_id' => (int)$binding['employee_id'],
                    'outlet_id' => (int)$binding['outlet_id'],
                    'terminal_id' => (int)$binding['terminal_id'],
                    'cashier_session_id' => max(0, (int)($sale['cashier_session_id'] ?? 0)),
                    'terminal_device_key_hash' => hash('sha256', (string)$binding['device_key']),
                    'import_status' => 'PROCESSING',
                    'request_json' => json_encode($storedSale, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES),
                    'received_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $importRowId = $inserted ? (int)$this->db->insert_id() : 0;
                if ($importRowId <= 0) {
                    throw new RuntimeException('Bukti import gagal dicatat. Sinkronkan ulang dengan event yang sama.');
                }
            }

            $this->validate_cash_sale($sale, $binding, $session);
            $orderPayload = $sale;
            unset($orderPayload['id'], $orderPayload['server_id'], $orderPayload['order_no']);
            $orderPayload['outlet_id'] = (int)$binding['outlet_id'];
            $orderPayload['terminal_id'] = (int)$session['terminal_id'];
            $orderPayload['origin_terminal_id'] = (int)$binding['terminal_id'];
            $orderPayload['mobile_backup_mode'] = (int)$session['terminal_id'] !== (int)$binding['terminal_id'];
            $orderPayload['require_active_session'] = true;
            $serverNow = date('Y-m-d H:i:s');
            $orderLock = $this->Pos_model->acquire_pos_order_no_lock(date('Ymd', strtotime($serverNow)));
            if (!$this->db->trans_begin()) {
                throw new RuntimeException('Transaksi import tidak dapat dimulai.');
            }
            $transactionStarted = true;

            $saved = $this->Pos_model->save_order_draft($orderPayload, (int)$binding['employee_id'], true, $serverNow);
            if (empty($saved['ok']) || (int)($saved['id'] ?? 0) <= 0) {
                throw new RuntimeException((string)($saved['message'] ?? 'Order offline tidak dapat disimpan Finance.'));
            }
            $serverOrderId = (int)$saved['id'];
            $header = $this->db->from('pos_order')->where('id', $serverOrderId)->limit(1)->get()->row_array();
            $amount = round((float)$sale['paid_amount'], 2);
            if (!$header || abs(round((float)$header['grand_total'], 2) - $amount) > 0.009) {
                throw new RuntimeException('Total order Finance berbeda dari bukti pembayaran lokal. Periksa transaksi sebelum posting.');
            }

            $confirmation = $this->confirm_order($serverOrderId, (int)$binding['employee_id']);
            $paymentLock = $this->Pos_model->acquire_pos_payment_no_lock(date('Ymd', strtotime($serverNow)));
            $payment = $this->Pos_model->save_cashier_payment([
                'order_id' => $serverOrderId,
                'payment_method_ids' => [(int)$sale['payment_method_id']],
                'paid_amounts' => [round((float)$sale['received_amount'], 2)],
                'reference_nos' => [(string)$sale['local_payment_no']],
                'notes' => 'Import tunai offline ' . (string)$sale['local_payment_no'],
            ], (int)$binding['employee_id'], $serverNow);
            if (empty($payment['ok']) || (int)($payment['id'] ?? 0) <= 0) {
                throw new RuntimeException((string)($payment['message'] ?? 'Pembayaran offline tidak dapat diposting Finance.'));
            }
            if (
                (string)($payment['order_status'] ?? '') !== 'PAID'
                || abs(round((float)($payment['paid_now'] ?? 0), 2) - $amount) > 0.009
                || abs(round((float)($payment['change_total'] ?? 0), 2) - round((float)$sale['change_amount'], 2)) > 0.009
                || abs(round((float)($payment['deposit_applied_amount'] ?? 0), 2)) > 0.009
                || round((float)($payment['remaining_due'] ?? 0), 2) > 0.009
            ) {
                throw new RuntimeException('Hasil posting pembayaran berbeda dari bukti tunai lokal. Periksa transaksi sebelum posting.');
            }

            $response = [
                'client_event_id' => $eventId,
                'posting_status' => 'POSTED',
                'server_order_id' => $serverOrderId,
                'server_order_no' => (string)$saved['order_no'],
                'server_payment_id' => (int)$payment['id'],
                'server_receipt_no' => (string)$payment['payment_no'],
                'stock_commit_status' => (string)$confirmation['stock_commit_status'],
                'local_order_uuid' => (string)$request['local_order_uuid'],
                'ledger_uuid' => (string)$request['ledger_uuid'],
            ];
            $this->db->where('id', $importRowId)->update('pos_mobile_offline_sale_import', [
                'import_status' => 'POSTED',
                'server_order_id' => $serverOrderId,
                'server_payment_id' => (int)$payment['id'],
                'error_message' => null,
                'response_json' => json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES),
                'processed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($this->db->trans_status() === false || !$this->db->trans_commit()) {
                throw new RuntimeException('Import gagal di-commit Finance.');
            }
            $transactionStarted = false;
            return ['ok' => true, 'http_status' => 200, 'response' => $response];
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->trans_rollback();
            }
            if ($importRowId > 0) {
                $response = [
                    'client_event_id' => $eventId,
                    'posting_status' => 'NEEDS_ACTION',
                    'message' => $e->getMessage(),
                    'local_order_uuid' => (string)$request['local_order_uuid'],
                ];
                $this->db->where('id', $importRowId)->update('pos_mobile_offline_sale_import', [
                    'import_status' => 'NEEDS_ACTION',
                    'error_message' => $e->getMessage(),
                    'response_json' => json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                return ['ok' => true, 'http_status' => 202, 'response' => $response];
            }
            return ['ok' => false, 'http_status' => 503, 'message' => $e->getMessage()];
        } finally {
            if ($orderLock !== '') {
                $this->Pos_model->release_pos_order_no_lock($orderLock);
            }
            if ($paymentLock !== '') {
                $this->Pos_model->release_pos_payment_no_lock($paymentLock);
            }
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
            $this->db->db_debug = $previousDbDebug;
        }
    }

    private function validate_cash_sale(array $sale, array $binding, ?array $session): void
    {
        if (!$session || strtoupper((string)($session['session_status'] ?? '')) !== 'OPEN') {
            throw new RuntimeException('Sesi kasir asal belum aktif di Finance. Transaksi perlu rekonsiliasi.');
        }
        if (
            (int)($sale['id'] ?? 0) > 0
            || (int)($sale['outlet_id'] ?? 0) !== (int)$binding['outlet_id']
            || (int)($sale['terminal_id'] ?? 0) !== (int)$binding['terminal_id']
            || (int)($sale['cashier_session_id'] ?? 0) !== (int)$session['id']
            || (int)($session['outlet_id'] ?? 0) !== (int)$binding['outlet_id']
            || (int)($session['employee_id'] ?? 0) !== (int)$binding['employee_id']
            || empty($sale['confirm_order'])
            || !is_array($sale['lines'] ?? null)
            || empty($sale['lines'])
            || count($sale['lines']) > 120
        ) {
            throw new RuntimeException('Order lokal tidak sesuai dengan sesi, outlet, atau perangkat yang terikat.');
        }
        if (
            (int)($sale['payment_method_id'] ?? 0) <= 0
            || !preg_match('/^PAY-[0-9a-fA-F-]{36}$/', (string)($sale['local_payment_uuid'] ?? ''))
            || !preg_match('/^OFF-[0-9a-fA-F-]{36}$/', (string)($sale['local_payment_no'] ?? ''))
            || strcasecmp((string)($sale['local_payment_no'] ?? ''), 'OFF-' . substr((string)($sale['local_payment_uuid'] ?? ''), 4)) !== 0
            || round((float)($sale['paid_amount'] ?? 0), 2) <= 0
            || round((float)($sale['received_amount'] ?? 0), 2) + 0.009 < round((float)$sale['paid_amount'], 2)
            || abs(round((float)($sale['received_amount'] ?? 0) - (float)$sale['paid_amount'], 2) - round((float)($sale['change_amount'] ?? 0), 2)) > 0.009
            || abs(round((float)($sale['grand_total'] ?? 0), 2) - round((float)$sale['paid_amount'], 2)) > 0.009
        ) {
            throw new RuntimeException('Bukti nominal tunai lokal tidak konsisten.');
        }
        foreach (['discount_amount', 'promo_amount', 'voucher_amount', 'point_redeem_amount', 'deposit_applied_amount', 'compliment_amount'] as $adjustment) {
            if (abs((float)($sale[$adjustment] ?? 0)) > 0.009) {
                throw new RuntimeException('Penjualan offline dengan promo, voucher, poin, atau DP perlu rekonsiliasi khusus.');
            }
        }
        $method = $this->db->from('pos_payment_method')
            ->where('id', (int)$sale['payment_method_id'])->limit(1)->get()->row_array();
        if (!$method || (int)($method['is_active'] ?? 0) !== 1 || strtoupper((string)($method['method_type'] ?? '')) !== 'CASH') {
            throw new RuntimeException('Metode tunai lokal tidak aktif atau bukan tunai di Finance.');
        }
    }

    private function confirm_order(int $orderId, int $employeeId): array
    {
        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $employeeId);
        if (empty($resolved['ok'])) {
            throw new RuntimeException((string)($resolved['message'] ?? 'Snapshot stok order offline gagal disiapkan.'));
        }
        $stockStatus = 'NOT_REQUIRED';
        $snapshotId = 0;
        if (!empty($resolved['lines'])) {
            $this->load->library('PosStockCommitService');
            $this->load->library('PosRuntimeJobService');
            $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)$resolved['header'], (array)$resolved['lines']);
            if (empty($snapshot['ok'])) {
                throw new RuntimeException((string)($snapshot['message'] ?? 'Snapshot stok order offline gagal dibuat.'));
            }
            $snapshotId = (int)$snapshot['id'];
            $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, $snapshotId, $employeeId, [
                'event_source' => 'OFFLINE_CASH_SALE_IMPORT', 'event_id' => $orderId,
            ]);
            if (empty($queued['ok'])) {
                throw new RuntimeException((string)($queued['message'] ?? 'Queue stok order offline gagal dibuat.'));
            }
            $marked = $this->posstockcommitservice->mark_queued($snapshotId);
            if (empty($marked['ok'])) {
                throw new RuntimeException((string)($marked['message'] ?? 'Snapshot stok order offline gagal diantrekan.'));
            }
            $stockStatus = 'QUEUED';
        }
        $finalized = $this->Pos_model->finalize_order_confirmation($orderId, $snapshotId, $employeeId, $stockStatus);
        if (empty($finalized['ok'])) {
            throw new RuntimeException((string)($finalized['message'] ?? 'Order offline gagal dikonfirmasi.'));
        }
        $this->load->model('Pos_order_monitor_model');
        $this->Pos_order_monitor_model->sync_order_tasks($orderId);
        return ['stock_commit_status' => $stockStatus, 'snapshot_id' => $snapshotId];
    }

    private function same_binding(array $row, array $binding): bool
    {
        return (int)$row['actor_user_id'] === (int)$binding['user_id']
            && (int)$row['actor_employee_id'] === (int)$binding['employee_id']
            && (int)$row['outlet_id'] === (int)$binding['outlet_id']
            && (int)$row['terminal_id'] === (int)$binding['terminal_id']
            && hash_equals((string)$row['terminal_device_key_hash'], hash('sha256', (string)$binding['device_key']));
    }
}
