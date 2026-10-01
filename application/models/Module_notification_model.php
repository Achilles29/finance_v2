<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Module_notification.php';

class Module_notification_model extends CI_Model
{
    private bool $schemaReady = false;

    public function ready(): bool
    {
        if ($this->schemaReady) return true;
        $required = [
            'app_notification_rule' => ['channel','event_code','is_enabled','order_start_id','targets_json','updated_by','updated_at','last_worker_at'],
            'app_notification_queue' => ['id','delivery_key','channel','event_code','source_id','target_key','destination','target_label','message_text','attachment_path','attachment_name','status','attempts','claimed_at','sent_at','last_error','created_by','created_at'],
        ];
        foreach ($required as $table => $columns) {
            if (!$this->db->table_exists($table) || array_diff($columns, $this->db->list_fields($table))) return false;
        }
        $unique = $this->db->query("SHOW INDEX FROM app_notification_queue WHERE Key_name='uq_notification_delivery'")->result_array();
        $primary = $this->db->query("SHOW INDEX FROM app_notification_rule WHERE Key_name='PRIMARY'")->result_array();
        $this->schemaReady = count($unique) === 1 && (int)$unique[0]['Non_unique'] === 0
            && $unique[0]['Column_name'] === 'delivery_key'
            && array_column($primary, 'Column_name') === ['channel','event_code'];
        return $this->schemaReady;
    }

    public function allowed(string $event = ''): bool
    {
        $ci =& get_instance();
        $ci->load->library('Feature_gate');
        $features = ['AUTOMATION_MESSAGING'];
        if ($event !== '') $features[] = ['SELF_ORDER' => 'SELF_ORDER', 'ONLINE_ORDER' => 'ONLINE_ORDER', 'DIVISION_REQUEST' => 'PROCUREMENT', 'DAILY_SALES' => 'SALES_REPORTING'][$event] ?? '__UNKNOWN__';
        foreach ($features as $feature) {
            if (empty($ci->feature_gate->decision($feature, ['source' => 'module_notification'])['allowed'])) return false;
        }
        return true;
    }

    public function available_targets(string $channel, bool $include_unavailable = false): array
    {
        $result = [];
        if ($channel === 'WA' && $this->db->table_exists('wa_group_map')) {
            // is_active controls inbound bot replies, not outbound module notifications.
            foreach ($this->db->from('wa_group_map')->order_by('group_name', 'ASC')->order_by('id', 'ASC')->get()->result_array() as $row) {
                if (preg_match('/\A[0-9-]+@g\.us\z/D', (string)$row['group_jid'])) {
                    $result['group:' . (int)$row['id']] = ['destination' => $row['group_jid'], 'label' => $row['group_name']];
                } elseif ($include_unavailable) {
                    // Display-only: validation and workers always use the default sendable list.
                    $result['group:' . (int)$row['id']] = ['destination' => '', 'label' => $row['group_name'], 'unavailable' => true];
                }
            }
        } elseif ($channel === 'TELEGRAM' && $this->db->table_exists('tg_target')) {
            foreach ($this->db->from('tg_target')->where('is_active', 1)->get()->result_array() as $row) {
                if (preg_match('/\A-?[0-9]{1,20}\z/D', (string)$row['chat_id'])) {
                    $result['chat:' . (int)$row['id']] = ['destination' => (string)$row['chat_id'], 'label' => $row['title']];
                }
            }
        }
        return $result;
    }

    public function rules(string $channel): array
    {
        $rules = [];
        foreach (Module_notification::events($channel) as $event => $title) {
            $rules[$event] = ['is_enabled' => 0, 'order_start_id' => 0, 'targets' => [], 'title' => $title];
        }
        if (!$this->ready()) return $rules;
        foreach ($this->db->from('app_notification_rule')->where('channel', $channel)->get()->result_array() as $row) {
            $event = (string)$row['event_code'];
            if (!isset($rules[$event])) continue;
            $targets = json_decode((string)$row['targets_json'], true);
            $rules[$event] = array_merge($rules[$event], $row, ['targets' => is_array($targets) ? $targets : []]);
        }
        return $rules;
    }

    private function lock(string $channel): bool
    {
        $row = $this->db->query('SELECT GET_LOCK(?, 0) AS acquired', [$this->lock_name($channel)])->row_array();
        return (int)($row['acquired'] ?? 0) === 1;
    }

    private function lock_name(string $channel): string
    {
        return 'finance-notify:' . substr(hash('sha256', (string)$this->db->database), 0, 20) . ':' . $channel;
    }

    private function unlock(string $channel): void
    {
        $this->db->query('SELECT RELEASE_LOCK(?)', [$this->lock_name($channel)]);
    }

    public function save_rules(string $channel, array $input, int $actor): void
    {
        if (!Module_notification::validChannel($channel) || !$this->ready() || !$this->allowed()) {
            throw new RuntimeException('Integrasi belum tersedia. Periksa lisensi dan migrasi notifikasi.');
        }
        $available = $this->available_targets($channel);
        $prepared = [];
        foreach (Module_notification::events($channel) as $event => $title) {
            $row = is_array($input[$event] ?? null) ? $input[$event] : [];
            if (isset($row['phones']) && !is_string($row['phones'])) throw new InvalidArgumentException('Nomor tujuan harus berupa teks.');
            $targets = Module_notification::targets($row['targets'] ?? [], (string)($row['phones'] ?? ''), $available, $channel);
            $enabled = ($row['enabled'] ?? '') === '1';
            if ($enabled && (!$targets || !$this->allowed($event))) {
                throw new InvalidArgumentException($title . ': pilih tujuan terdaftar yang tersedia dan pastikan modul termasuk dalam lisensi.');
            }
            if ($enabled && $channel === 'WA' && !$this->personal_enabled() && $this->has_phones($targets)) {
                throw new InvalidArgumentException('Pengiriman WA pribadi masih dikunci untuk perlindungan akun. Pilih grup WA; pembukaan kanal pribadi memerlukan review terpisah.');
            }
            $prepared[$event] = ['enabled' => $enabled, 'targets' => $targets];
        }
        if (!$this->lock($channel)) throw new RuntimeException('Pengiriman sedang berjalan. Coba simpan kembali sebentar lagi.');
        if (!$this->db->trans_begin()) {
            $this->unlock($channel);
            throw new RuntimeException('Pengaturan belum dapat disimpan. Coba kembali.');
        }
        try {
            $old = $this->rules($channel);
            $max = $this->db->query('SELECT COALESCE(MAX(id), 0) AS last_id FROM pos_order')->row_array();
            foreach ($prepared as $event => $row) {
                // No retroactive broadcasts on enable or recipient change. A plain save preserves pending work.
                $changed = empty($old[$event]['is_enabled']) || $old[$event]['targets'] !== $row['targets'];
                $start = $row['enabled'] && $changed ? (int)$max['last_id'] : (int)$old[$event]['order_start_id'];
                $ok = $this->db->query('INSERT INTO app_notification_rule (channel,event_code,is_enabled,order_start_id,targets_json,updated_by,updated_at) VALUES (?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled),order_start_id=VALUES(order_start_id),targets_json=VALUES(targets_json),updated_by=VALUES(updated_by),updated_at=NOW()', [
                    $channel, $event, $row['enabled'] ? 1 : 0, $start,
                    json_encode($row['targets'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $actor ?: null,
                ]);
                if (!$ok) throw new RuntimeException('Pengaturan belum tersimpan. Coba kembali.');
            }
            if (!$this->db->trans_status()) throw new RuntimeException('Pengaturan belum tersimpan. Coba kembali.');
            if (!$this->db->trans_commit()) throw new RuntimeException('Pengaturan belum tersimpan. Coba kembali.');
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            throw $error;
        } finally {
            $this->unlock($channel);
        }
    }

    private function personal_enabled(): bool
    {
        // Preserve Whatsapp::PERSONAL_OUTBOUND_ENABLED=false: account-protection policy,
        // not an invitation to bypass it through a new module or an invented env switch.
        return false;
    }

    private function has_phones(array $targets): bool
    {
        foreach ($targets as $key => $_) if (strpos($key, 'phone:') === 0) return true;
        return false;
    }

    private function channel_enabled(string $channel): bool
    {
        if ($channel === 'TELEGRAM') {
            $ci =& get_instance();
            $ci->load->model('Telegram_model');
            return $ci->Telegram_model->ready() && $ci->Telegram_model->is_enabled();
        }
        return $channel === 'WA';
    }

    public function enabled_channels(string $event = 'DIVISION_REQUEST'): array
    {
        if (!$this->ready() || !$this->allowed($event)) return [];
        $channels = [];
        foreach (['WA', 'TELEGRAM'] as $channel) {
            $rule = $this->rules($channel)[$event] ?? [];
            if (!empty($rule['is_enabled']) && $this->live_targets($channel, $rule) && $this->channel_enabled($channel)) $channels[] = $channel;
        }
        return $channels;
    }

    private function live_targets(string $channel, array $rule): array
    {
        $available = $this->available_targets($channel);
        $result = [];
        foreach ($rule['targets'] as $key => $target) {
            if ($channel === 'WA' && $this->personal_enabled() && preg_match('/\Aphone:([1-9][0-9]{7,14})\z/D', $key, $match)) {
                if (($target['destination'] ?? '') === $match[1]) $result[$key] = $target;
            } elseif (isset($available[$key]) && $available[$key]['destination'] === ($target['destination'] ?? null)) {
                $result[$key] = $target;
            }
        }
        return $result;
    }

    public function enqueue_division(string $channel, array $detail, int $actor, array $attachment = []): array
    {
        if (!in_array($channel, $this->enabled_channels(), true)) throw new RuntimeException('Kanal notifikasi ini belum diaktifkan atau tujuannya sudah tidak aktif.');
        $header = $detail['header'];
        if (!in_array(strtoupper((string)$header['status']), ['SUBMITTED', 'VERIFIED'], true)) {
            throw new RuntimeException('Hanya pengajuan terkirim atau terverifikasi yang dapat dibagikan.');
        }
        $id = (int)$header['id'];
        $message = Module_notification::message('DIVISION_REQUEST', $header, $detail['lines'], site_url('procurement/division-po-sr/detail/' . $id));
        // PDF is a distinct delivery revision from the legacy text-only
        // notification, but remains idempotent when the same button is clicked again.
        // Permit one corrected PDF after the old stock-less template, while
        // preserving deduplication for repeated clicks on the corrected version.
        $revision = hash('sha256', $message . '|' . (!empty($attachment['name']) ? 'pdf-v2-stock' : 'text-v1'));
        $rule = $this->rules($channel)['DIVISION_REQUEST'];
        $count = 0;
        foreach ($this->live_targets($channel, $rule) as $key => $target) {
            $count += $this->enqueue($channel, 'DIVISION_REQUEST', $id, $key, $target, $message, $actor, $revision, $attachment);
        }
        return ['ok' => true, 'message' => $count > 0
            ? 'Pengajuan masuk antrean ' . $channel . '. Status kirim tersedia di pengaturan kanal.'
            : 'Pengajuan yang sama sudah tercatat di antrean. Tidak dikirim ganda; periksa status di pengaturan kanal.'];
    }

    public function enqueue_daily_sales(array $filters, string $outletName, string $snapshot, int $actor, array $attachment): array
    {
        require_once APPPATH . 'libraries/Daily_sales_pdf.php';
        $filters = Daily_sales_pdf::filters($filters['date'] ?? null, $filters['outlet_id'] ?? null);
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $snapshot)) throw new InvalidArgumentException('Snapshot laporan tidak valid.');
        Daily_sales_pdf::assertAttachment($attachment);
        if (!$this->lock('WA')) throw new RuntimeException('Pengiriman WA sedang berjalan. Coba kembali sebentar lagi.');
        try {
            if (!in_array('WA', $this->enabled_channels('DAILY_SALES'), true)) {
                throw new RuntimeException('Aktifkan Daily Sales (PDF) dan pilih grup penerima di pengaturan WA terlebih dahulu.');
            }
            $message = "Daily Sales POS\nTanggal: " . $filters['date'] . "\nOutlet: " . Module_notification::clean($outletName)
                . "\nPDF sesuai snapshot laporan saat tombol Kirim WA ditekan.\nBuka laporan (perlu login): "
                . site_url('pos/reports/daily-sales?' . http_build_query($filters));
            $revision = hash('sha256', 'daily-sales-pdf-v1|' . json_encode($filters) . '|' . $snapshot);
            $count = 0;
            foreach ($this->live_targets('WA', $this->rules('WA')['DAILY_SALES']) as $key => $target) {
                $count += $this->enqueue('WA', 'DAILY_SALES', (int)str_replace('-', '', $filters['date']), $key, $target, $message, $actor, $revision, $attachment);
            }
            return ['message' => $count > 0
                ? 'PDF Daily Sales masuk antrean WA. Periksa status pengiriman di pengaturan WA.'
                : 'PDF dengan data yang sama sudah tercatat. Tidak dikirim ganda; periksa status di pengaturan WA.'];
        } finally {
            $this->unlock('WA');
        }
    }

    private function enqueue(string $channel, string $event, int $id, string $key, array $target, string $message, int $actor = 0, string $revision = '', array $attachment = []): int
    {
        $ok = $this->db->query("INSERT INTO app_notification_queue (delivery_key,channel,event_code,source_id,target_key,destination,target_label,message_text,attachment_path,attachment_name,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE message_text=IF(status='PENDING', VALUES(message_text), message_text), attachment_path=IF(status='PENDING', VALUES(attachment_path), attachment_path), attachment_name=IF(status='PENDING', VALUES(attachment_name), attachment_name)", [
            Module_notification::key($channel, $event, $id, $key, $revision), $channel, $event, $id, $key,
            $target['destination'], mb_substr($target['label'], 0, 190), $message,
            $attachment['path'] ?? null, $attachment['name'] ?? null, $actor ?: null,
        ]);
        if (!$ok) throw new RuntimeException('Notifikasi belum masuk antrean. Coba kembali.');
        return $this->db->affected_rows() === 1 ? 1 : 0;
    }

    public function recent(string $channel): array
    {
        if (!$this->ready()) return [];
        return $this->db->select('id,event_code,source_id,target_label,status,last_error,created_at,sent_at')->from('app_notification_queue')->where('channel', $channel)->order_by('id', 'DESC')->limit(30)->get()->result_array();
    }

    public function retry(string $channel, int $id): void
    {
        if (!$this->ready() || !$this->allowed()) throw new RuntimeException('Integrasi belum tersedia.');
        // UNKNOWN may already have reached its recipient. Never automatically resend it.
        $this->db->where('id', $id)->where('channel', $channel)->where('status', 'FAILED')
            ->update('app_notification_queue', ['status' => 'PENDING', 'last_error' => null]);
        if ($this->db->affected_rows() !== 1) throw new RuntimeException('Hanya pesan gagal yang pasti belum terkirim yang dapat dicoba kembali.');
    }

    private function discover_orders(string $channel): int
    {
        $count = 0;
        foreach (['SELF_ORDER' => 'SELF_ORDER', 'ONLINE_ORDER' => 'DELIVERY'] as $event => $orderChannel) {
            $rule = $this->rules($channel)[$event];
            if (empty($rule['is_enabled']) || !$this->allowed($event)) continue;
            foreach ($this->live_targets($channel, $rule) as $key => $target) {
                $rows = $this->db->query("SELECT o.*, po.outlet_name FROM pos_order o LEFT JOIN pos_outlet po ON po.id=o.outlet_id WHERE o.order_channel=? AND o.id>? AND o.status NOT IN ('VOID','CANCELLED','REJECTED','REFUND_FULL') AND EXISTS (SELECT 1 FROM pos_order_line l WHERE l.order_id=o.id AND l.qty>0 AND l.line_status NOT IN ('VOID','REFUNDED_FULL')) AND NOT EXISTS (SELECT 1 FROM app_notification_queue q WHERE q.channel=? AND q.event_code=? AND q.source_id=o.id AND q.target_key=?) ORDER BY o.id LIMIT 30", [$orderChannel, (int)$rule['order_start_id'], $channel, $event, $key])->result_array();
                foreach ($rows as $header) {
                    $id = (int)$header['id'];
                    $lines = $this->db->select('COALESCE(b.bundle_name,p.product_name,\'Item\') AS product_name,l.qty', false)
                        ->from('pos_order_line l')->join('mst_product p', 'p.id=l.product_id', 'left')
                        ->join('pos_product_bundle b', 'b.id=l.bundle_id', 'left')->where('l.order_id', $id)
                        ->where('l.line_type !=', 'BUNDLE_ITEM')->where('l.line_status !=', 'VOID')
                        ->order_by('l.id')->limit(13)->get()->result_array();
                    $url = site_url($event === 'SELF_ORDER' ? 'pos/self-order/orders' : 'pos/online-food/orders');
                    $count += $this->enqueue($channel, $event, $id, $key, $target, Module_notification::message($event, $header, $lines, $url));
                }
            }
        }
        return $count;
    }

    /** Existing CLI channel workers call this; no HTTP dispatch, no new cron required if they already run. */
    public function run(string $channel, callable $send): array
    {
        if (!Module_notification::validChannel($channel) || !$this->ready() || !$this->allowed() || !$this->channel_enabled($channel)) return ['queued' => 0, 'processed' => 0, 'state' => 'DISABLED'];
        if (!$this->lock($channel)) return ['queued' => 0, 'processed' => 0, 'state' => 'BUSY'];
        $result = ['queued' => 0, 'processed' => 0, 'state' => 'OK'];
        try {
            $this->db->where('channel', $channel)->update('app_notification_rule', ['last_worker_at' => date('Y-m-d H:i:s')]);
            // The previous worker may have sent the message before dying. Retain evidence; do not retry blindly.
            $this->db->where('channel', $channel)->where('status', 'PROCESSING')->update('app_notification_queue', ['status' => 'UNKNOWN', 'last_error' => 'Proses sebelumnya terputus. Periksa tujuan sebelum mengirim ulang.']);
            $result['queued'] = $this->discover_orders($channel);
            $rows = $this->db->from('app_notification_queue')->where('channel', $channel)->where('status', 'PENDING')->order_by('id')->limit(10)->get()->result_array();
            $started = microtime(true);
            foreach ($rows as $row) {
                if (microtime(true) - $started > 40) break;
                $rule = $this->rules($channel)[$row['event_code']] ?? [];
                $targets = $rule ? $this->live_targets($channel, $rule) : [];
                $valid = !empty($rule['is_enabled']) && $this->allowed($row['event_code'])
                    && isset($targets[$row['target_key']]) && $targets[$row['target_key']]['destination'] === $row['destination'];
                if ($valid && $row['event_code'] === 'DIVISION_REQUEST') {
                    $source = $this->db->select('status')->from('pur_division_request')->where('id', $row['source_id'])->get()->row_array();
                    $valid = $source && in_array($source['status'], ['SUBMITTED', 'VERIFIED'], true);
                } elseif ($valid && $row['event_code'] === 'DAILY_SALES') {
                    // A report is a snapshot, not a pos_order ID. Never apply the order cutoff to it.
                    require_once APPPATH . 'libraries/Daily_sales_pdf.php';
                    try {
                        Daily_sales_pdf::assertAttachment(['path' => $row['attachment_path'], 'name' => $row['attachment_name']]);
                        $valid = $channel === 'WA';
                    } catch (RuntimeException $error) {
                        $valid = false;
                    }
                } elseif ($valid) {
                    $source = $this->db->select('status')->from('pos_order')->where('id', $row['source_id'])->get()->row_array();
                    $valid = (int)$row['source_id'] > (int)$rule['order_start_id'] && $source && !in_array($source['status'], ['VOID', 'CANCELLED', 'REJECTED', 'REFUND_FULL'], true);
                }
                if (!$valid || !$this->channel_enabled($channel)) {
                    $this->finish((int)$row['id'], 'CANCELLED', 'Kanal, modul, tujuan, atau dokumen sudah tidak aktif.');
                    continue;
                }
                $ok = $this->db->where('id', $row['id'])->where('status', 'PENDING')->update('app_notification_queue', ['status' => 'PROCESSING', 'attempts' => (int)$row['attempts'] + 1, 'claimed_at' => date('Y-m-d H:i:s')]);
                if (!$ok || $this->db->affected_rows() !== 1) continue;
                try {
                    $delivery = $send($row);
                    $status = !empty($delivery['ok']) ? 'SENT' : (($delivery['status'] ?? '') === 'FAILED' ? 'FAILED' : 'UNKNOWN');
                } catch (Throwable $error) {
                    $status = 'UNKNOWN';
                }
                $this->finish((int)$row['id'], $status, $status === 'SENT' ? null : ($status === 'UNKNOWN' ? 'Hasil pengiriman belum pasti. Periksa chat tujuan; tidak dicoba ulang otomatis.' : 'Pesan ditolak kanal. Periksa koneksi dan tujuan lalu coba kembali.'));
                $result['processed']++;
            }
        } finally {
            $this->unlock($channel);
        }
        return $result;
    }

    private function finish(int $id, string $status, ?string $error): void
    {
        $this->db->where('id', $id)->update('app_notification_queue', ['status' => $status, 'last_error' => $error, 'sent_at' => $status === 'SENT' ? date('Y-m-d H:i:s') : null]);
    }
}
