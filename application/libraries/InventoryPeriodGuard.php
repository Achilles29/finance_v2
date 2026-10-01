<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Central period gate for inventory writers.
 *
 * Inventory writes fail closed until the foundation migration is available.
 */
class InventoryPeriodGuard
{
    /** @var CI_Controller */
    private $ci;
    /** @var array<string, array> */
    private $periodCache = [];
    /** @var bool|null */
    private $readyCache = null;
    /** @var array<string, bool> */
    private $cutoffWriteContexts = [];

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function isReady(): bool
    {
        if ($this->readyCache === null) {
            $this->readyCache = $this->ci->db->table_exists('inv_stock_period');
        }
        return $this->readyCache;
    }

    public function assertOpen(string $stockDomain, string $eventDate, string $operation = 'transaksi'): array
    {
        $stockDomain = strtoupper(trim($stockDomain));
        if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain stok tidak valid untuk period guard.'];
        }

        $periodMonth = $this->normalizeMonth($eventDate);
        if ($periodMonth === null) {
            return ['ok' => false, 'message' => 'Tanggal ' . $operation . ' tidak valid.'];
        }

        if (!$this->isReady()) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_PERIOD_SCHEMA_NOT_READY',
                'guard_active' => false,
                'message' => 'Period guard belum siap karena migration inventory belum dijalankan.',
            ];
        }

        $cacheKey = $this->cacheKey($stockDomain, $periodMonth);
        $transactionActive = $this->transactionActive();
        if (!$transactionActive && array_key_exists($cacheKey, $this->periodCache)) {
            $row = $this->periodCache[$cacheKey];
        } elseif ($transactionActive) {
            $query = $this->ci->db->query(
                'SELECT id, status, period_month FROM inv_stock_period WHERE stock_domain = ? AND period_month = ? LIMIT 1 FOR UPDATE',
                [$stockDomain, $periodMonth]
            );
            if (!$query) {
                return ['ok' => false, 'code' => 'INVENTORY_PERIOD_LOCK_FAILED', 'message' => 'Periode stok gagal dikunci.'];
            }
            $row = $query->row_array();
        } else {
            $row = $this->ci->db
                ->select('id, status, period_month')
                ->from('inv_stock_period')
                ->where('stock_domain', $stockDomain)
                ->where('period_month', $periodMonth)
                ->limit(1)
                ->get()
                ->row_array();
            $this->periodCache[$cacheKey] = $row ?: [];
        }

        $status = strtoupper(trim((string)($row['status'] ?? 'OPEN')));
        if ($status === 'CLOSING' && $this->hasCutoffWriteContext($stockDomain, $periodMonth)) {
            return [
                'ok' => true,
                'guard_active' => true,
                'cutoff_context' => true,
                'period_id' => (int)($row['id'] ?? 0),
                'status' => $status,
                'period_month' => $periodMonth,
            ];
        }
        if (in_array($status, ['CLOSING', 'CLOSED'], true)) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_PERIOD_CLOSED',
                'message' => 'Periode stok ' . date('m/Y', strtotime($periodMonth))
                    . ' sudah ditutup. ' . ucfirst($operation) . ' tidak dapat diposting atau di-void tanpa reopen resmi.',
                'period_id' => (int)($row['id'] ?? 0),
                'status' => $status,
            ];
        }

        return [
            'ok' => true,
            'guard_active' => true,
            'period_id' => (int)($row['id'] ?? 0),
            'status' => $status,
            'period_month' => $periodMonth,
        ];
    }

    public function ensureOpen(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $db = $this->ci->db;
        $transactionActive = $this->transactionActive();
        if (!$transactionActive) {
            $check = $this->assertOpen($stockDomain, $eventDate, 'transaksi');
            if (!($check['ok'] ?? false) || !$this->isReady()) {
                return $check;
            }
            if (!empty($check['period_id'])) {
                return $check;
            }
            $periodMonth = (string)$check['period_month'];
        } else {
            $stockDomain = strtoupper(trim($stockDomain));
            if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true)) {
                return ['ok' => false, 'message' => 'Domain stok tidak valid untuk period guard.'];
            }
            $periodMonth = $this->normalizeMonth($eventDate);
            if ($periodMonth === null) {
                return ['ok' => false, 'message' => 'Tanggal transaksi tidak valid.'];
            }
            if (!$this->isReady()) {
                return [
                    'ok' => false,
                    'code' => 'INVENTORY_PERIOD_SCHEMA_NOT_READY',
                    'guard_active' => false,
                    'message' => 'Period guard belum siap karena migration inventory belum dijalankan.',
                ];
            }
        }

        $ownsTransaction = !$transactionActive;
        if ($ownsTransaction) {
            $started = $db->trans_begin();
            if (!$started || !$db->trans_status()) {
                $db->trans_rollback();
                return ['ok' => false, 'code' => 'INVENTORY_PERIOD_LOCK_FAILED', 'message' => 'Transaksi periode stok gagal dimulai.'];
            }
        }

        // This single write either creates the unique (domain, month) row or
        // locks the concurrent/existing row without changing its lifecycle.
        // It also waits behind a historical writer's newer-period range lock.
        $previousDbDebug = isset($db->db_debug) ? (bool)$db->db_debug : false;
        $db->db_debug = false;
        $upserted = $db->query(
            'INSERT INTO inv_stock_period '
            . '(stock_domain, period_month, status, close_mode, notes, created_by) '
            . "VALUES (?, ?, 'OPEN', 'MONTHLY_OPNAME', ?, ?) "
            . 'ON DUPLICATE KEY UPDATE id = id',
            [
                strtoupper(trim($stockDomain)),
                $periodMonth,
                $note !== '' ? substr($note, 0, 255) : null,
                $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            ]
        );
        $db->db_debug = $previousDbDebug;
        if (!$upserted || !$db->trans_status()) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            return ['ok' => false, 'code' => 'INVENTORY_PERIOD_LOCK_FAILED', 'message' => 'Periode stok aktif gagal dibuat atau dikunci.'];
        }

        $this->forgetPeriod($stockDomain, $periodMonth);
        $result = $this->assertOpen($stockDomain, $eventDate, 'transaksi');
        if (!($result['ok'] ?? false) || empty($result['period_id']) || !$db->trans_status()) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            if (!$db->trans_status()) {
                return ['ok' => false, 'code' => 'INVENTORY_PERIOD_LOCK_FAILED', 'message' => 'Transaksi periode stok gagal setelah pembacaan ulang.'];
            }
            return ($result['ok'] ?? false)
                ? ['ok' => false, 'code' => 'INVENTORY_PERIOD_LOCK_FAILED', 'message' => 'Periode stok aktif gagal dibaca ulang.']
                : $result;
        }
        if ($ownsTransaction) {
            $committed = $db->trans_commit();
            if (!$committed || !$db->trans_status()) {
                $db->trans_rollback();
                return ['ok' => false, 'code' => 'INVENTORY_PERIOD_LOCK_FAILED', 'message' => 'Transaksi periode stok gagal diselesaikan.'];
            }
        }
        return $result;
    }

    public function lockActivePeriodsForWrite(array $pairs): array
    {
        if (!$this->transactionActive()) {
            return ['ok' => false, 'code' => 'INVENTORY_TRANSACTION_REQUIRED', 'message' => 'Transaksi database aktif wajib tersedia sebelum mengunci periode stok.'];
        }

        $normalized = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                return ['ok' => false, 'message' => 'Pasangan domain/tanggal periode stok tidak valid.'];
            }
            $domain = strtoupper(trim((string)($pair['stock_domain'] ?? $pair['domain'] ?? ($pair[0] ?? ''))));
            $date = (string)($pair['event_date'] ?? $pair['period_month'] ?? $pair['date'] ?? ($pair[1] ?? ''));
            $month = $this->normalizeMonth($date);
            if (!in_array($domain, ['COMPONENT', 'MATERIAL'], true) || $month === null) {
                return ['ok' => false, 'message' => 'Pasangan domain/tanggal periode stok tidak valid.'];
            }
            $normalized[$this->cacheKey($domain, $month)] = ['stock_domain' => $domain, 'period_month' => $month];
        }
        usort($normalized, static function (array $left, array $right): int {
            $rank = ['COMPONENT' => 0, 'MATERIAL' => 1];
            $domainOrder = $rank[$left['stock_domain']] <=> $rank[$right['stock_domain']];
            return $domainOrder !== 0 ? $domainOrder : strcmp($left['period_month'], $right['period_month']);
        });

        $locked = [];
        foreach ($normalized as $pair) {
            $result = $this->ensureActiveMonthOpen($pair['stock_domain'], $pair['period_month']);
            if (!($result['ok'] ?? false) || empty($result['period_id'])) {
                return ($result['ok'] ?? false)
                    ? ['ok' => false, 'code' => 'INVENTORY_PERIOD_LOCK_FAILED', 'message' => 'Periode stok aktif tidak ditemukan setelah penguncian.']
                    : $result;
            }
            $locked[] = $result;
        }
        return ['ok' => true, 'periods' => $locked];
    }

    /**
     * Automatically creates a record only for the active calendar month.
     * Once a newer period exists, ordinary writers must not backdate stock
     * because its delta would miss the newer month's carried-forward opening.
     */
    public function ensureActiveMonthOpen(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $stockDomain = strtoupper(trim($stockDomain));
        if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain stok tidak valid untuk period guard.'];
        }
        $periodMonth = $this->normalizeMonth($eventDate);
        if ($periodMonth === null) {
            return ['ok' => false, 'message' => 'Tanggal transaksi tidak valid.'];
        }
        if (!$this->isReady()) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_PERIOD_SCHEMA_NOT_READY',
                'guard_active' => false,
                'message' => 'Period guard belum siap karena migration inventory belum dijalankan.',
            ];
        }

        $activeMonth = date('Y-m-01');
        if ($periodMonth > $activeMonth) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_FUTURE_PERIOD_WRITE',
                'message' => 'Transaksi stok bertanggal masa depan tidak dapat mengubah stok live.',
                'period_month' => $periodMonth,
                'active_month' => $activeMonth,
            ];
        }

        // Current-month creation must enter ensureOpen() without a missing-key
        // locking read; its atomic upsert is the first locking DML.
        if ($periodMonth === $activeMonth) {
            return $this->ensureOpen($stockDomain, $eventDate, $actorUserId, $note);
        }

        $check = $this->assertOpen($stockDomain, $eventDate, 'transaksi');
        if (!($check['ok'] ?? false)) {
            return $check;
        }

        if ($periodMonth < $activeMonth) {
            if (!empty($check['cutoff_context'])) {
                return $check;
            }

            $normalizedDomain = strtoupper(trim($stockDomain));
            if ($this->transactionActive()) {
                // assertOpen() has already locked the exact historical key.
                // This next-key/range lock on the same unique index prevents a
                // concurrent rollover INSERT until the inventory write commits.
                $query = $this->ci->db->query(
                    'SELECT id, period_month, status FROM inv_stock_period '
                    . 'WHERE stock_domain = ? AND period_month > ? '
                    . 'ORDER BY period_month ASC LIMIT 1 FOR UPDATE',
                    [$normalizedDomain, $periodMonth]
                );
                if (!$query) {
                    return [
                        'ok' => false,
                        'code' => 'INVENTORY_PERIOD_LOCK_FAILED',
                        'message' => 'Rentang periode stok yang lebih baru gagal dikunci.',
                    ];
                }
                $newerPeriod = $query->row_array();
            } else {
                $newerPeriod = $this->ci->db
                    ->select('id, period_month, status')
                    ->from('inv_stock_period')
                    ->where('stock_domain', $normalizedDomain)
                    ->where('period_month >', $periodMonth)
                    ->order_by('period_month', 'ASC')
                    ->limit(1)
                    ->get()
                    ->row_array();
            }
            if (!empty($newerPeriod)) {
                return [
                    'ok' => false,
                    'code' => 'INVENTORY_BACKDATE_AFTER_ROLLOVER',
                    'message' => 'Transaksi stok bulan ' . date('m/Y', strtotime($periodMonth))
                        . ' ditolak karena periode ' . date('m/Y', strtotime((string)$newerPeriod['period_month']))
                        . ' sudah dimulai. Catat koreksi pada bulan aktif agar opening, saldo, dan lot tetap satu alur.',
                    'period_id' => (int)($check['period_id'] ?? 0),
                    'period_month' => $periodMonth,
                    'newer_period_id' => (int)($newerPeriod['id'] ?? 0),
                    'newer_period_month' => (string)($newerPeriod['period_month'] ?? ''),
                ];
            }

            $status = strtoupper(trim((string)($check['status'] ?? 'OPEN')));
            if ($status !== 'REOPENED') {
                return [
                    'ok' => false,
                    'code' => 'INVENTORY_PERIOD_REOPEN_REQUIRED',
                    'message' => 'Periode stok ' . date('m/Y', strtotime($periodMonth))
                        . ' bukan bulan aktif. Reopen resmi diperlukan sebelum transaksi historis dapat diproses.',
                    'period_id' => (int)($check['period_id'] ?? 0),
                    'period_month' => $periodMonth,
                    'status' => $status,
                ];
            }

            return $check;
        }

        return $check;
    }

    public function closePeriod(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $db = $this->ci->db;
        $ownsTransaction = !$this->transactionActive();
        if ($ownsTransaction) {
            $db->trans_begin();
        }
        $open = $this->ensureOpen($stockDomain, $eventDate, $actorUserId, $note);
        if (!($open['ok'] ?? false) || !$this->isReady()) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            return $open;
        }

        $db->where('id', (int)($open['period_id'] ?? 0))->where('status', 'CLOSING')->update('inv_stock_period', [
            'status' => 'CLOSED',
            'close_mode' => 'MONTHLY_OPNAME',
            'closed_by' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'closed_at' => date('Y-m-d H:i:s'),
            'notes' => $note !== '' ? substr($note, 0, 255) : null,
        ]);
        if ($db->affected_rows() !== 1 || !$db->trans_status()) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            return ['ok' => false, 'message' => 'Periode stok berubah oleh proses lain sebelum penutupan selesai.'];
        }
        if ($ownsTransaction) {
            $db->trans_commit();
        }
        $this->forgetPeriod($stockDomain, (string)($open['period_month'] ?? ''));
        return [
            'ok' => true,
            'period_id' => (int)($open['period_id'] ?? 0),
            'period_month' => $open['period_month'] ?? null,
            'status' => 'CLOSED',
        ];
    }

    /**
     * Marks a period as closing before a controlled cut-off writer starts.
     * Normal inventory requests are blocked while the official writer has a
     * short-lived in-process context to finish its own work.
     */
    public function beginClosingPeriod(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $db = $this->ci->db;
        $ownsTransaction = !$this->transactionActive();
        if ($ownsTransaction) {
            $db->trans_begin();
        }
        $open = $this->ensureOpen($stockDomain, $eventDate, $actorUserId, $note);
        if (!($open['ok'] ?? false) || !$this->isReady()) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            return $open;
        }

        $status = strtoupper(trim((string)($open['status'] ?? 'OPEN')));
        if (!in_array($status, ['OPEN', 'REOPENED'], true)) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            return [
                'ok' => false,
                'message' => 'Periode stok tidak dapat mulai ditutup dari status ' . ($status ?: '-') . '.',
                'period_id' => (int)($open['period_id'] ?? 0),
                'status' => $status,
            ];
        }

        $periodId = (int)($open['period_id'] ?? 0);
        if ($periodId <= 0) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            return ['ok' => false, 'message' => 'ID periode stok tidak ditemukan saat memulai cut-off.'];
        }

        $this->ci->db
            ->where('id', $periodId)
            ->where_in('status', ['OPEN', 'REOPENED'])
            ->update('inv_stock_period', [
                'status' => 'CLOSING',
                'close_mode' => 'MONTHLY_OPNAME',
                'notes' => $note !== '' ? substr($note, 0, 255) : 'Cut-off stok resmi sedang diproses.',
            ]);

        if ($this->ci->db->affected_rows() !== 1) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            $this->forgetPeriod($stockDomain, (string)($open['period_month'] ?? ''));
            return [
                'ok' => false,
                'message' => 'Periode stok berubah oleh proses lain. Muat ulang halaman sebelum mencoba cut-off lagi.',
                'period_id' => $periodId,
            ];
        }

        if (!$db->trans_status()) {
            if ($ownsTransaction) {
                $db->trans_rollback();
            }
            return ['ok' => false, 'message' => 'Transaksi perubahan status periode stok gagal.'];
        }
        if ($ownsTransaction) {
            $db->trans_commit();
        }

        $this->forgetPeriod($stockDomain, (string)($open['period_month'] ?? ''));
        return [
            'ok' => true,
            'period_id' => $periodId,
            'period_month' => $open['period_month'] ?? null,
            'status' => 'CLOSING',
        ];
    }

    public function reopenPeriod(
        string $stockDomain,
        string $eventDate,
        ?int $actorUserId = null,
        string $note = '',
        bool $allowCutoffRollbackAfterRollover = false
    ): array
    {
        if (!$this->isReady()) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_PERIOD_SCHEMA_NOT_READY',
                'guard_active' => false,
                'message' => 'Period guard belum siap. Jalankan migration inventory terlebih dahulu.',
            ];
        }
        $periodMonth = $this->normalizeMonth($eventDate);
        if ($periodMonth === null) {
            return ['ok' => false, 'message' => 'Bulan reopen tidak valid.'];
        }
        $stockDomain = strtoupper(trim($stockDomain));
        if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain stok tidak valid untuk reopen periode.'];
        }

        $newerPeriod = $this->ci->db
            ->select('id, period_month, status')
            ->from('inv_stock_period')
            ->where('stock_domain', $stockDomain)
            ->where('period_month >', $periodMonth)
            ->order_by('period_month', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();
        if (!empty($newerPeriod) && !$allowCutoffRollbackAfterRollover) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_REOPEN_AFTER_ROLLOVER_BLOCKED',
                'message' => 'Periode ' . date('m/Y', strtotime($periodMonth))
                    . ' tidak dapat dibuka kembali karena periode '
                    . date('m/Y', strtotime((string)$newerPeriod['period_month']))
                    . ' sudah tersedia. Gunakan koreksi bulan aktif atau proses repair terkontrol.',
                'period_month' => $periodMonth,
                'newer_period_id' => (int)($newerPeriod['id'] ?? 0),
                'newer_period_month' => (string)($newerPeriod['period_month'] ?? ''),
            ];
        }

        $this->ci->db->where('stock_domain', $stockDomain)->where('period_month', $periodMonth)->update('inv_stock_period', [
            'status' => 'REOPENED',
            'reopened_by' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'reopened_at' => date('Y-m-d H:i:s'),
            'notes' => $note !== '' ? substr($note, 0, 255) : 'Reopen resmi inventory.',
        ]);
        $this->forgetPeriod($stockDomain, $periodMonth);
        return $this->assertOpen($stockDomain, $eventDate, 'reopen');
    }

    /**
     * Allows only the current official cut-off request to write its source
     * month while all ordinary requests remain blocked by CLOSING.
     */
    public function beginCutoffWriteContext(string $stockDomain, string $eventDate): bool
    {
        $stockDomain = strtoupper(trim($stockDomain));
        $periodMonth = $this->normalizeMonth($eventDate);
        if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true) || $periodMonth === null) {
            return false;
        }

        $this->cutoffWriteContexts[$this->cacheKey($stockDomain, $periodMonth)] = true;
        $this->forgetPeriod($stockDomain, $periodMonth);
        return true;
    }

    public function endCutoffWriteContext(string $stockDomain, string $eventDate): void
    {
        $periodMonth = $this->normalizeMonth($eventDate);
        if ($periodMonth === null) {
            return;
        }

        unset($this->cutoffWriteContexts[$this->cacheKey($stockDomain, $periodMonth)]);
        $this->forgetPeriod($stockDomain, $periodMonth);
    }

    private function cacheKey(string $stockDomain, string $periodMonth): string
    {
        return strtoupper(trim($stockDomain)) . '|' . $periodMonth;
    }

    private function forgetPeriod(string $stockDomain, string $periodMonth): void
    {
        if ($periodMonth === '') {
            return;
        }
        unset($this->periodCache[$this->cacheKey($stockDomain, $periodMonth)]);
    }

    private function hasCutoffWriteContext(string $stockDomain, string $periodMonth): bool
    {
        return !empty($this->cutoffWriteContexts[$this->cacheKey($stockDomain, $periodMonth)]);
    }

    private function transactionActive(): bool
    {
        return method_exists($this->ci->db, 'trans_active') && $this->ci->db->trans_active();
    }

    private function normalizeMonth(string $date): ?string
    {
        $date = trim($date);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $matches)) {
            return null;
        }
        if (!checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1])) {
            return null;
        }
        return $matches[1] . '-' . $matches[2] . '-01';
    }
}
