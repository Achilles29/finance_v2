<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Coordinates the existing monthly opname generators into one safe official
 * cut-off operation. It never invents a second stock writer; it only calls
 * the existing material/component generators after a preflight succeeds.
 */
class InventoryCutoffService
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Inventory_control_model');
    }

    public function isReady(): bool
    {
        return $this->ci->db->table_exists('inv_stock_period')
            && $this->ci->db->table_exists('inv_stock_cutoff_run');
    }

    public function schemaMessage(): string
    {
        if (!$this->ci->db->table_exists('inv_stock_period')) {
            return 'Fondasi periode stok belum tersedia. Jalankan SQL 2026-08-13a terlebih dahulu.';
        }
        if (!$this->ci->db->table_exists('inv_stock_cutoff_run')) {
            return 'Audit posting cut-off belum tersedia. Jalankan SQL 2026-08-20a_inventory_official_cutoff_run_audit.sql terlebih dahulu.';
        }
        return '';
    }

    /**
     * Read-only validation. The official post action reruns this method so a
     * screen that has been open for a while cannot use stale data.
     */
    public function preflight(array $period): array
    {
        $domain = strtoupper(trim((string)($period['stock_domain'] ?? '')));
        $month = $this->normalizeMonth((string)($period['period_month'] ?? ''));
        $periodId = (int)($period['id'] ?? 0);
        $status = strtoupper(trim((string)($period['status'] ?? 'OPEN')));
        $openingMonth = $month !== null ? date('Y-m-01', strtotime($month . ' +1 month')) : null;
        $blocks = [];
        $warnings = [];

        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true) || $month === null || $periodId <= 0) {
            return [
                'ok' => false,
                'can_post' => false,
                'message' => 'Data periode stok tidak valid untuk posting cut-off.',
                'blocks' => ['Periode stok tidak memiliki domain, bulan, atau ID yang valid.'],
                'warnings' => [],
            ];
        }

        if (!$this->isReady()) {
            return [
                'ok' => false,
                'can_post' => false,
                'message' => $this->schemaMessage(),
                'blocks' => [$this->schemaMessage()],
                'warnings' => [],
                'stock_domain' => $domain,
                'period_month' => $month,
                'opening_month' => $openingMonth,
            ];
        }

        if ($month >= date('Y-m-01')) {
            $blocks[] = 'Cut-off resmi hanya boleh untuk bulan yang sudah selesai. Bulan berjalan belum boleh diposting.';
        }
        if ($status === 'CLOSED') {
            $blocks[] = 'Periode ini sudah terkunci. Buka kembali periode secara resmi sebelum memperbaiki atau memposting ulang.';
        } elseif ($status === 'CLOSING') {
            $blocks[] = 'Periode sedang diproses oleh cut-off lain. Tunggu proses selesai atau lakukan recovery jika proses benar-benar gagal.';
        } elseif (!in_array($status, ['OPEN', 'REOPENED'], true)) {
            $blocks[] = 'Status periode tidak mendukung posting cut-off: ' . ($status ?: '-') . '.';
        }

        $basePreflight = $this->ci->Inventory_control_model->period_close_preflight($domain, $month);
        foreach ((array)($basePreflight['warnings'] ?? []) as $warning) {
            $warnings[] = (string)$warning;
        }

        $preview = $this->ci->Inventory_control_model->period_cutoff_preview($domain, $month, 25);
        if (empty($preview['ok'])) {
            $blocks[] = (string)($preview['message'] ?? 'Simulasi stok awal belum dapat dibaca.');
        } else {
            $summary = (array)($preview['summary'] ?? []);
            if ((int)($summary['source_row_count'] ?? 0) <= 0) {
                $blocks[] = 'Tidak ada saldo bulan sumber yang dapat dipakai untuk membentuk opname dan stok awal.';
            }
            if ((int)($summary['negative_closing_count'] ?? 0) > 0) {
                $blocks[] = (int)$summary['negative_closing_count'] . ' saldo akhir masih minus. Perbaiki stok fisik atau defisitnya sebelum cut-off.';
            }
            foreach ((array)($preview['warnings'] ?? []) as $warning) {
                $warnings[] = (string)$warning;
            }
        }

        $futureMovement = $openingMonth !== null ? $this->nextMonthMovementSummary($domain, $openingMonth) : ['count' => 0];
        if ((int)($futureMovement['count'] ?? 0) > 0) {
            $blocks[] = 'Bulan berikutnya sudah memiliki ' . (int)$futureMovement['count']
                . ' mutasi ' . ($domain === 'MATERIAL' ? 'bahan baku' : 'component')
                . '. Posting opening terlambat dapat mengubah dasar saldo bulan yang sedang berjalan, sehingga proses ini sengaja ditolak.';
        }

        $manualOpening = $openingMonth !== null ? $this->manualOpeningSummary($domain, $openingMonth, $month) : ['count' => 0];
        if ((int)($manualOpening['count'] ?? 0) > 0) {
            $blocks[] = 'Ada ' . (int)$manualOpening['count'] . ' opening bulan berikutnya yang bukan hasil auto carry-forward bulan ini. Tinjau atau pindahkan data manual tersebut terlebih dahulu agar tidak tertimpa.';
        }

        $posCommit = $this->posCommitSummary($month);
        if ((int)($posCommit['pending_count'] ?? 0) > 0) {
            $blocks[] = 'Masih ada ' . (int)$posCommit['pending_count'] . ' transaksi POS bulan sumber yang belum selesai commit stok.';
        }
        if ((int)($posCommit['failed_count'] ?? 0) > 0) {
            $blocks[] = 'Masih ada ' . (int)$posCommit['failed_count'] . ' commit stok POS bulan sumber berstatus gagal.';
        }

        $warnings = array_values(array_unique(array_filter(array_map('trim', $warnings), static function (string $value): bool {
            return $value !== '';
        })));
        $canPost = empty($blocks);

        return [
            'ok' => true,
            'can_post' => $canPost,
            'message' => $canPost
                ? 'Periode siap diposting sebagai cut-off resmi.'
                : 'Periode belum aman diposting sebagai cut-off resmi.',
            'stock_domain' => $domain,
            'period_id' => $periodId,
            'period_month' => $month,
            'opening_month' => $openingMonth,
            'period_status' => $status,
            'preview' => $preview,
            'base_preflight' => $basePreflight,
            'future_movement' => $futureMovement,
            'manual_opening' => $manualOpening,
            'pos_commit' => $posCommit,
            'requires_acknowledgement' => !empty($warnings),
            'warnings' => $warnings,
            'blocks' => $blocks,
        ];
    }

    /**
     * Runs the existing official generators and closes the source period only
     * after all generated opening rows have been verified.
     */
    public function post(array $period, int $actorUserId, string $sourceIp, string $notes, bool $acknowledged): array
    {
        $preflight = $this->preflight($period);
        if (empty($preflight['can_post'])) {
            return [
                'ok' => false,
                'message' => $this->preflightMessage($preflight),
                'preflight' => $preflight,
            ];
        }
        if (!empty($preflight['requires_acknowledgement']) && !$acknowledged) {
            return [
                'ok' => false,
                'message' => 'Centang bahwa Anda sudah membaca catatan cut-off sebelum memposting.',
                'preflight' => $preflight,
            ];
        }

        $domain = (string)$preflight['stock_domain'];
        $month = (string)$preflight['period_month'];
        $periodId = (int)$preflight['period_id'];
        $this->ci->load->library('InventoryPeriodGuard');

        $begin = $this->ci->inventoryperiodguard->beginClosingPeriod(
            $domain,
            $month,
            $actorUserId > 0 ? $actorUserId : null,
            'Cut-off stok resmi sedang diproses.'
        );
        if (empty($begin['ok'])) {
            return [
                'ok' => false,
                'message' => (string)($begin['message'] ?? 'Gagal mengamankan periode untuk cut-off.'),
                'preflight' => $preflight,
            ];
        }

        $run = $this->createRun($periodId, $preflight, $actorUserId, $notes);
        if (empty($run['ok'])) {
            $this->ci->inventoryperiodguard->reopenPeriod(
                $domain,
                $month,
                $actorUserId > 0 ? $actorUserId : null,
                'Cut-off dibatalkan karena audit run tidak dapat dibuat.',
                true
            );
            return [
                'ok' => false,
                'message' => (string)($run['message'] ?? 'Gagal membuat audit posting cut-off.'),
                'preflight' => $preflight,
            ];
        }

        $runId = (int)$run['id'];
        $runNo = (string)$run['cutoff_no'];
        $steps = [];
        $cutoffContextStarted = false;
        try {
            $cutoffContextStarted = $this->ci->inventoryperiodguard->beginCutoffWriteContext($domain, $month);
            if (!$cutoffContextStarted) {
                throw new RuntimeException('Gagal menyiapkan konteks writer cut-off.');
            }

            if ($domain === 'MATERIAL') {
                $this->ci->load->model('Purchase_model');
                $steps['warehouse'] = $this->ci->Purchase_model->generate_monthly_opname_and_opening([
                    'stock_scope' => 'WAREHOUSE',
                    'month' => $month,
                ], $actorUserId, $sourceIp);
                if (empty($steps['warehouse']['ok'])) {
                    throw new RuntimeException((string)($steps['warehouse']['message'] ?? 'Generate opname gudang gagal.'));
                }

                $steps['division'] = $this->ci->Purchase_model->generate_monthly_opname_and_opening([
                    'stock_scope' => 'DIVISION',
                    'month' => $month,
                ], $actorUserId, $sourceIp);
                if (empty($steps['division']['ok'])) {
                    throw new RuntimeException((string)($steps['division']['message'] ?? 'Generate opname divisi gagal.'));
                }
            } else {
                $this->ci->load->model('Production_model');
                $steps['component'] = $this->ci->Production_model->generate_component_monthly_opname_and_opening([
                    'month' => substr($month, 0, 7),
                ], $actorUserId);
                if (empty($steps['component']['ok'])) {
                    throw new RuntimeException((string)($steps['component']['message'] ?? 'Generate opname component gagal.'));
                }
            }

            $verification = $this->verifyOpeningPrepared($domain, $month);
            if (empty($verification['ok'])) {
                throw new RuntimeException((string)($verification['message'] ?? 'Verifikasi opening cut-off gagal.'));
            }

            $close = $this->ci->inventoryperiodguard->closePeriod(
                $domain,
                $month,
                $actorUserId > 0 ? $actorUserId : null,
                'Cut-off resmi ' . $runNo . ($notes !== '' ? ' | ' . $notes : '')
            );
            if (empty($close['ok'])) {
                throw new RuntimeException((string)($close['message'] ?? 'Opening berhasil dibuat tetapi periode gagal dikunci.'));
            }

            if ($cutoffContextStarted) {
                $this->ci->inventoryperiodguard->endCutoffWriteContext($domain, $month);
                $cutoffContextStarted = false;
            }

            $counts = $this->stepCounts($steps);
            $payload = [
                'preflight' => $this->compactPreflight($preflight),
                'steps' => $steps,
                'verification' => $verification,
                'period_close' => $close,
            ];
            $this->finishRun($runId, 'POSTED', $counts, $payload, null);
            $this->recordAudit('INVENTORY_CUTOFF_POSTED', $runId, $runNo, $actorUserId, $sourceIp, $payload, 'Cut-off stok resmi berhasil diposting.');

            return [
                'ok' => true,
                'run_id' => $runId,
                'cutoff_no' => $runNo,
                'message' => 'Cut-off resmi ' . $runNo . ' berhasil. Opname dan stok awal ' . date('F Y', strtotime((string)$preflight['opening_month'])) . ' sudah dibentuk, lalu periode sumber dikunci.',
                'steps' => $steps,
                'verification' => $verification,
            ];
        } catch (Throwable $e) {
            if ($cutoffContextStarted) {
                $this->ci->inventoryperiodguard->endCutoffWriteContext($domain, $month);
            }

            $counts = $this->stepCounts($steps);
            $status = !empty($steps) ? 'PARTIAL' : 'FAILED';
            $payload = [
                'preflight' => $this->compactPreflight($preflight),
                'steps' => $steps,
                'error' => $e->getMessage(),
            ];
            $this->finishRun($runId, $status, $counts, $payload, $e->getMessage());
            $this->ci->inventoryperiodguard->reopenPeriod(
                $domain,
                $month,
                $actorUserId > 0 ? $actorUserId : null,
                'Cut-off ' . $runNo . ' gagal/partial. Periksa audit run sebelum mencoba ulang.',
                true
            );
            $this->recordAudit('INVENTORY_CUTOFF_' . $status, $runId, $runNo, $actorUserId, $sourceIp, $payload, 'Cut-off stok resmi tidak selesai.');
            log_message('error', 'inventory official cutoff failed ' . $runNo . ': ' . $e->getMessage());

            return [
                'ok' => false,
                'run_id' => $runId,
                'cutoff_no' => $runNo,
                'status' => $status,
                'message' => 'Cut-off tidak selesai. Periode dibuka kembali agar tidak terkunci setengah jalan. Periksa riwayat run ' . $runNo . ': ' . $e->getMessage(),
                'steps' => $steps,
            ];
        }
    }

    private function verifyOpeningPrepared(string $domain, string $month): array
    {
        $preview = $this->ci->Inventory_control_model->period_cutoff_preview($domain, $month, 5);
        if (empty($preview['ok'])) {
            return ['ok' => false, 'message' => (string)($preview['message'] ?? 'Opening setelah posting tidak dapat diverifikasi.')];
        }

        $summary = (array)($preview['summary'] ?? []);
        $candidate = (int)($summary['candidate_opening_count'] ?? 0);
        $prepared = (int)($summary['prepared_opening_count'] ?? 0);
        $negative = (int)($summary['negative_closing_count'] ?? 0);
        if ($negative > 0) {
            return ['ok' => false, 'message' => $negative . ' saldo akhir masih minus setelah generator dijalankan.'];
        }
        if ($candidate !== $prepared) {
            return ['ok' => false, 'message' => 'Opening belum lengkap: ' . $prepared . ' dari ' . $candidate . ' calon saldo awal ditemukan.'];
        }

        return [
            'ok' => true,
            'candidate_opening_count' => $candidate,
            'prepared_opening_count' => $prepared,
            'opening_month' => (string)($preview['opening_month'] ?? ''),
        ];
    }

    private function createRun(int $periodId, array $preflight, int $actorUserId, string $notes): array
    {
        $last = $this->ci->db
            ->select_max('attempt_no', 'last_attempt_no')
            ->from('inv_stock_cutoff_run')
            ->where('period_id', $periodId)
            ->get()
            ->row_array() ?: [];
        $attemptNo = max(0, (int)($last['last_attempt_no'] ?? 0)) + 1;
        $domain = (string)$preflight['stock_domain'];
        $month = (string)$preflight['period_month'];
        $cutoffNo = 'CUT-' . $domain . '-' . date('Ym', strtotime($month)) . '-' . strtoupper(substr(hash('sha256', uniqid((string)$periodId, true)), 0, 8));
        $summary = (array)(($preflight['preview']['summary'] ?? []));

        $this->ci->db->insert('inv_stock_cutoff_run', [
            'cutoff_no' => $cutoffNo,
            'period_id' => $periodId,
            'stock_domain' => $domain,
            'period_month' => $month,
            'opening_month' => (string)$preflight['opening_month'],
            'status' => 'RUNNING',
            'attempt_no' => $attemptNo,
            'preview_source_rows' => (int)($summary['source_row_count'] ?? 0),
            'preview_candidate_rows' => (int)($summary['candidate_opening_count'] ?? 0),
            'preview_zero_rows' => (int)($summary['zero_closing_count'] ?? 0),
            'preview_negative_rows' => (int)($summary['negative_closing_count'] ?? 0),
            'preview_total_value' => round((float)($summary['candidate_total_value'] ?? 0), 2),
            'notes' => $notes !== '' ? substr($notes, 0, 255) : null,
            'started_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);
        $runId = (int)$this->ci->db->insert_id();
        if ($runId <= 0) {
            $error = $this->ci->db->error();
            return ['ok' => false, 'message' => 'Gagal membuat audit run cut-off: ' . (string)($error['message'] ?? 'unknown error')];
        }

        return ['ok' => true, 'id' => $runId, 'cutoff_no' => $cutoffNo];
    }

    private function finishRun(int $runId, string $status, array $counts, array $payload, ?string $errorMessage): void
    {
        $this->ci->db->where('id', $runId)->update('inv_stock_cutoff_run', [
            'status' => $status,
            'generated_opname_rows' => (int)($counts['opname_rows'] ?? 0),
            'generated_opening_rows' => (int)($counts['opening_rows'] ?? 0),
            'generated_monthly_rows' => (int)($counts['monthly_rows'] ?? 0),
            'result_payload' => $this->json($payload),
            'error_message' => $errorMessage !== null ? substr($errorMessage, 0, 1000) : null,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function stepCounts(array $steps): array
    {
        $counts = ['opname_rows' => 0, 'opening_rows' => 0, 'monthly_rows' => 0];
        foreach ($steps as $step) {
            $data = (array)($step['data'] ?? []);
            $counts['opname_rows'] += (int)($data['opname_rows'] ?? $data['generated_rows'] ?? 0);
            $counts['opening_rows'] += (int)($data['opening_rows'] ?? $data['carried_rows'] ?? 0);
            $counts['monthly_rows'] += (int)($data['opening_monthly_rows'] ?? $data['generated_rows'] ?? 0);
        }
        return $counts;
    }

    private function recordAudit(string $action, int $runId, string $runNo, int $actorUserId, string $sourceIp, array $payload, string $notes): void
    {
        if (!$this->ci->db->table_exists('aud_transaction_log')) {
            return;
        }

        $this->ci->db->insert('aud_transaction_log', [
            'module_code' => 'INVENTORY',
            'action_code' => substr($action, 0, 40),
            'entity_table' => 'inv_stock_cutoff_run',
            'entity_id' => $runId,
            'transaction_no' => $runNo,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'source_ip' => $sourceIp !== '' ? substr($sourceIp, 0, 45) : null,
            'after_payload' => $this->json($payload),
            'notes' => substr($notes, 0, 255),
        ]);
    }

    private function nextMonthMovementSummary(string $domain, string $openingMonth): array
    {
        $table = $domain === 'MATERIAL' ? 'inv_stock_movement_log' : 'inv_component_movement_log';
        if (!$this->ci->db->table_exists($table)) {
            return ['count' => 0, 'table' => $table, 'sample_dates' => []];
        }

        $end = date('Y-m-01', strtotime($openingMonth . ' +1 month'));
        $count = (int)$this->ci->db
            ->from($table)
            ->where('movement_date >=', $openingMonth)
            ->where('movement_date <', $end)
            ->count_all_results();
        $samples = $this->ci->db
            ->select('movement_date')
            ->from($table)
            ->where('movement_date >=', $openingMonth)
            ->where('movement_date <', $end)
            ->order_by('movement_date', 'ASC')
            ->limit(5)
            ->get()
            ->result_array();

        return [
            'count' => $count,
            'table' => $table,
            'sample_dates' => array_values(array_unique(array_filter(array_map(static function (array $row): string {
                return (string)($row['movement_date'] ?? '');
            }, $samples)))),
        ];
    }

    private function manualOpeningSummary(string $domain, string $openingMonth, string $sourceMonth): array
    {
        if ($domain === 'MATERIAL') {
            $tables = ['inv_warehouse_stock_opening_snapshot', 'inv_division_stock_opening_snapshot'];
            $count = 0;
            foreach ($tables as $table) {
                if (!$this->ci->db->table_exists($table)) {
                    continue;
                }
                $count += (int)$this->ci->db
                    ->from($table)
                    ->where('snapshot_month', $openingMonth)
                    ->where("COALESCE(source_type, '') <> 'AUTO_REBUILD'", null, false)
                    ->count_all_results();
            }
            return ['count' => $count, 'tables' => $tables];
        }

        if (!$this->ci->db->table_exists('inv_component_monthly_opening')) {
            return ['count' => 0, 'tables' => []];
        }

        $sourceMonthEnd = date('Y-m-t', strtotime($sourceMonth));
        $count = (int)$this->ci->db
            ->from('inv_component_monthly_opening')
            ->where('month_key', $openingMonth)
            ->group_start()
                ->where('source_type <>', 'OPNAME')
                ->or_where('source_month_key IS NULL', null, false)
                ->or_where('source_month_key <', $sourceMonth)
                ->or_where('source_month_key >', $sourceMonthEnd)
            ->group_end()
            ->count_all_results();
        return ['count' => $count, 'tables' => ['inv_component_monthly_opening']];
    }

    private function posCommitSummary(string $month): array
    {
        $summary = ['pending_count' => 0, 'failed_count' => 0];
        if (!$this->ci->db->table_exists('pos_order')) {
            return $summary;
        }

        $dateColumn = $this->ci->db->field_exists('confirmed_at', 'pos_order') ? 'confirmed_at' : 'ordered_at';
        $dateFrom = $month . ' 00:00:00';
        $dateTo = date('Y-m-t 23:59:59', strtotime($month));
        if ($this->ci->db->field_exists('stock_commit_status', 'pos_order')) {
            $summary['pending_count'] = (int)$this->ci->db
                ->from('pos_order')
                ->where($dateColumn . ' >=', $dateFrom)
                ->where($dateColumn . ' <=', $dateTo)
                ->where('stock_commit_status', 'PENDING')
                ->count_all_results();
        }

        if ($this->ci->db->table_exists('pos_stock_commit')) {
            $summary['failed_count'] = (int)$this->ci->db
                ->from('pos_stock_commit sc')
                ->join('pos_order o', 'o.id = sc.order_id', 'inner')
                ->where('o.' . $dateColumn . ' >=', $dateFrom)
                ->where('o.' . $dateColumn . ' <=', $dateTo)
                ->where('sc.commit_status', 'FAILED')
                ->count_all_results();
        }

        return $summary;
    }

    private function compactPreflight(array $preflight): array
    {
        return [
            'stock_domain' => $preflight['stock_domain'] ?? null,
            'period_month' => $preflight['period_month'] ?? null,
            'opening_month' => $preflight['opening_month'] ?? null,
            'warnings' => $preflight['warnings'] ?? [],
            'blocks' => $preflight['blocks'] ?? [],
            'future_movement' => $preflight['future_movement'] ?? [],
            'manual_opening' => $preflight['manual_opening'] ?? [],
            'pos_commit' => $preflight['pos_commit'] ?? [],
            'preview_summary' => $preflight['preview']['summary'] ?? [],
        ];
    }

    private function preflightMessage(array $preflight): string
    {
        $blocks = array_values((array)($preflight['blocks'] ?? []));
        if (!empty($blocks)) {
            return 'Cut-off belum dapat diposting: ' . implode(' ', $blocks);
        }
        return (string)($preflight['message'] ?? 'Cut-off belum dapat diposting.');
    }

    private function normalizeMonth(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-01', $timestamp);
    }

    private function json(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? '{"ok":false,"message":"payload tidak dapat diserialisasi"}' : $json;
    }
}
