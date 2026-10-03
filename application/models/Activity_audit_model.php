<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read-only activity registry.
 *
 * This model deliberately exposes metadata only. Transaction payload snapshots
 * can contain business-sensitive fields and remain outside this operational
 * registry; the entity, action, reference, actor, IP, and timestamp are enough
 * to find the originating business record.
 */
class Activity_audit_model extends CI_Model
{
    public function list_users(): array
    {
        if (!$this->db->table_exists('auth_user')) {
            return [];
        }

        return $this->db
            ->select('id, username, is_active')
            ->from('auth_user')
            ->order_by('username', 'ASC')
            ->get()
            ->result_array();
    }

    public function summary(array $filters): array
    {
        [$union, $params] = $this->activity_union();
        if ($union === '') {
            $filePageViews = $this->file_page_event_count($filters);
            return ['total' => $filePageViews, 'page_views' => $filePageViews, 'logins' => 0, 'transactions' => 0];
        }

        [$where, $whereParams] = $this->filter_clause($filters);
        $rows = $this->db->query(
            "SELECT event_kind, COUNT(*) AS total FROM ({$union}) activity {$where} GROUP BY event_kind",
            array_merge($params, $whereParams)
        )->result_array();

        $summary = ['total' => 0, 'page_views' => 0, 'logins' => 0, 'transactions' => 0];
        foreach ($rows as $row) {
            $count = (int)($row['total'] ?? 0);
            $summary['total'] += $count;
            if (($row['event_kind'] ?? '') === 'PAGE_VIEW') {
                $summary['page_views'] = $count;
            } elseif (($row['event_kind'] ?? '') === 'LOGIN') {
                $summary['logins'] = $count;
            } elseif (($row['event_kind'] ?? '') === 'TRANSACTION') {
                $summary['transactions'] = $count;
            }
        }
        $filePageViews = $this->file_page_event_count($filters);
        $summary['total'] += $filePageViews;
        $summary['page_views'] += $filePageViews;

        return $summary;
    }

    public function count_events(array $filters): int
    {
        [$union, $params] = $this->activity_union();
        if ($union === '') {
            return $this->file_page_event_count($filters);
        }
        [$where, $whereParams] = $this->filter_clause($filters);
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM ({$union}) activity {$where}",
            array_merge($params, $whereParams)
        )->row_array();

        return (int)($row['total'] ?? 0) + $this->file_page_event_count($filters);
    }

    public function list_events(array $filters, int $limit = 50, int $offset = 0): array
    {
        [$union, $params] = $this->activity_union();
        if ($union === '') {
            return array_slice($this->file_page_events($filters), $offset, $limit);
        }
        [$where, $whereParams] = $this->filter_clause($filters);
        $limit = min(100, max(10, $limit));
        $offset = max(0, $offset);
        $needed = $offset + $limit;
        $rows = $this->db->query(
            "SELECT * FROM ({$union}) activity {$where}
             ORDER BY event_at DESC, event_id DESC
             LIMIT {$needed}",
            array_merge($params, $whereParams)
        )->result_array();

        $rows = array_merge($rows, $this->file_page_events($filters, $needed));
        usort($rows, static function(array $a, array $b): int {
            $byTime = strcmp((string)($b['event_at'] ?? ''), (string)($a['event_at'] ?? ''));
            return $byTime !== 0 ? $byTime : ((int)($b['event_id'] ?? 0) <=> (int)($a['event_id'] ?? 0));
        });
        $rows = array_slice($rows, $offset, $limit);
        foreach ($rows as &$row) {
            $row['device_label'] = $this->device_label((string)($row['user_agent'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    private function activity_union(): array
    {
        $sources = [];
        $params = [];
        if ($this->db->table_exists('aud_access_event')) {
            $legacyCutover = $this->access_event_log()->cutover();
            $legacyWindow = '';
            if ($legacyCutover !== null) {
                $legacyWindow = ' WHERE e.created_at < ?';
                $params[] = $legacyCutover;
            }
            $sources[] = "
                SELECT
                    'PAGE_VIEW' AS event_kind,
                    e.id AS event_id,
                    e.created_at AS event_at,
                    e.user_id,
                    COALESCE(u.username, CONCAT('User #', e.user_id)) AS username,
                    e.page_code,
                    e.route_path,
                    e.request_method,
                    'Membuka halaman' AS action_label,
                    'SYSTEM' AS module_code,
                    NULL AS entity_table,
                    NULL AS entity_id,
                    NULL AS transaction_no,
                    NULL AS ref_label,
                    NULL AS notes_preview,
                    e.ip_address,
                    e.user_agent,
                    'ACCESS_EVENT' AS device_source
                FROM aud_access_event e
                LEFT JOIN auth_user u ON u.id = e.user_id{$legacyWindow}";
        }

        if ($this->db->table_exists('auth_session_log')) {
            $sources[] = "
                SELECT
                    'LOGIN' AS event_kind,
                    s.id AS event_id,
                    s.login_at AS event_at,
                    s.user_id,
                    COALESCE(u.username, CONCAT('User #', s.user_id)) AS username,
                    NULL AS page_code,
                    NULL AS route_path,
                    NULL AS request_method,
                    CASE WHEN s.logout_at IS NULL THEN 'Login' ELSE 'Login (sesi sudah berakhir)' END AS action_label,
                    'AUTH' AS module_code,
                    'auth_session_log' AS entity_table,
                    s.id AS entity_id,
                    NULL AS transaction_no,
                    NULL AS ref_label,
                    CASE WHEN s.logout_at IS NULL THEN 'Sesi masih aktif' ELSE CONCAT('Logout: ', s.logout_at) END AS notes_preview,
                    s.ip_address,
                    s.user_agent,
                    'LOGIN_SESSION' AS device_source
                FROM auth_session_log s
                LEFT JOIN auth_user u ON u.id = s.user_id";
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $sources[] = "
                SELECT
                    'TRANSACTION' AS event_kind,
                    t.id AS event_id,
                    t.created_at AS event_at,
                    t.actor_user_id AS user_id,
                    COALESCE(u.username, CASE WHEN t.actor_user_id IS NULL THEN 'Sistem' ELSE CONCAT('User #', t.actor_user_id) END) AS username,
                    NULL AS page_code,
                    NULL AS route_path,
                    NULL AS request_method,
                    CONCAT(t.module_code, ' / ', t.action_code) AS action_label,
                    t.module_code,
                    t.entity_table,
                    t.entity_id,
                    t.transaction_no,
                    CONCAT_WS(' #', t.ref_table, t.ref_id) AS ref_label,
                    t.notes AS notes_preview,
                    t.source_ip AS ip_address,
                    (
                        SELECT s.user_agent
                        FROM auth_session_log s
                        WHERE s.user_id = t.actor_user_id
                          AND (t.source_ip IS NULL OR t.source_ip = '' OR s.ip_address = t.source_ip)
                          AND s.login_at <= t.created_at
                          AND (s.logout_at IS NULL OR s.logout_at >= t.created_at)
                        ORDER BY s.login_at DESC, s.id DESC
                        LIMIT 1
                    ) AS user_agent,
                    'MATCHED_SESSION' AS device_source
                FROM aud_transaction_log t
                LEFT JOIN auth_user u ON u.id = t.actor_user_id";
        }

        return [implode("\nUNION ALL\n", $sources), $params];
    }

    private function access_event_log(): Access_event_log
    {
        require_once APPPATH . 'libraries/Access_event_log.php';
        return new Access_event_log();
    }

    private function file_page_event_count(array $filters): int
    {
        $count = 0;
        foreach ($this->access_event_log()->eventsBetween(
            (string)($filters['from_at'] ?? date('Y-m-d 00:00:00')),
            (string)($filters['until_at'] ?? date('Y-m-d 00:00:00', strtotime('+1 day')))
        ) as $event) {
            if ($this->matches_file_event($event, $filters)) $count++;
        }
        return $count;
    }

    /** Return matching local page events, optionally retaining only the newest N. */
    private function file_page_events(array $filters, int $limit = 0): array
    {
        $events = [];
        foreach ($this->access_event_log()->eventsBetween(
            (string)($filters['from_at'] ?? date('Y-m-d 00:00:00')),
            (string)($filters['until_at'] ?? date('Y-m-d 00:00:00', strtotime('+1 day')))
        ) as $event) {
            if (!$this->matches_file_event($event, $filters)) continue;
            $event += [
                'entity_table' => null, 'entity_id' => null, 'transaction_no' => null,
                'ref_label' => null, 'notes_preview' => null,
            ];
            $events[] = $event;
            if ($limit > 0 && count($events) >= $limit * 2) {
                $this->sort_events($events);
                $events = array_slice($events, 0, $limit);
            }
        }
        $this->sort_events($events);
        return $limit > 0 ? array_slice($events, 0, $limit) : $events;
    }

    private function sort_events(array &$events): void
    {
        usort($events, static function(array $a, array $b): int {
            $byTime = strcmp((string)($b['event_at'] ?? ''), (string)($a['event_at'] ?? ''));
            return $byTime !== 0 ? $byTime : ((int)($b['event_id'] ?? 0) <=> (int)($a['event_id'] ?? 0));
        });
    }

    private function matches_file_event(array $event, array $filters): bool
    {
        $userId = max(0, (int)($filters['user_id'] ?? 0));
        if ($userId > 0 && (int)($event['user_id'] ?? 0) !== $userId) return false;
        $kind = strtoupper(trim((string)($filters['kind'] ?? '')));
        if ($kind !== '' && $kind !== 'PAGE_VIEW') return false;
        $query = trim((string)($filters['q'] ?? ''));
        if ($query === '') return true;
        foreach (['username', 'page_code', 'route_path', 'action_label', 'module_code'] as $field) {
            if (stripos((string)($event[$field] ?? ''), $query) !== false) return true;
        }
        return false;
    }

    private function filter_clause(array $filters): array
    {
        $where = ['WHERE event_at >= ? AND event_at < ?'];
        $params = [
            (string)($filters['from_at'] ?? date('Y-m-d 00:00:00')),
            (string)($filters['until_at'] ?? date('Y-m-d 00:00:00', strtotime('+1 day'))),
        ];
        $userId = max(0, (int)($filters['user_id'] ?? 0));
        if ($userId > 0) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        $kind = strtoupper(trim((string)($filters['kind'] ?? '')));
        if (in_array($kind, ['PAGE_VIEW', 'LOGIN', 'TRANSACTION'], true)) {
            $where[] = 'event_kind = ?';
            $params[] = $kind;
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(username LIKE ? OR page_code LIKE ? OR route_path LIKE ? OR action_label LIKE ? OR module_code LIKE ? OR entity_table LIKE ? OR transaction_no LIKE ? OR ref_label LIKE ? OR notes_preview LIKE ?)';
            $like = '%' . $this->db->escape_like_str(mb_substr($q, 0, 100)) . '%';
            $params = array_merge($params, array_fill(0, 9, $like));
        }

        return [implode(' AND ', $where), $params];
    }

    private function device_label(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Tidak tercatat';
        }
        $platform = 'Perangkat lain';
        foreach (['Android' => '/android/i', 'iOS' => '/iphone|ipad|ipod/i', 'Windows' => '/windows/i', 'macOS' => '/macintosh|mac os/i', 'Linux' => '/linux/i'] as $label => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $platform = $label;
                break;
            }
        }
        $browser = 'Browser lain';
        foreach (['Edge' => '/edg\//i', 'Chrome' => '/chrome|crios/i', 'Firefox' => '/firefox|fxios/i', 'Safari' => '/safari/i'] as $label => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $browser = $label;
                break;
            }
        }

        return $platform . ' / ' . $browser;
    }
}
