<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_printer_agent extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function bootstrap()
    {
        if (!$this->verify_printer_agent_key()) {
            return;
        }

        $this->load->model('Pos_print_model');
        $agentName = trim((string)$this->input->get('agent_name', true));
        if (!$this->Pos_print_model->agent_connection_ready()) {
            $this->bootstrap_error('Konfigurasi Koneksi Printer baru belum lengkap. Jalankan migration printer dan lengkapi Koneksi Printer terlebih dahulu.');
            return;
        }

        // The agent must consume the same connection records edited in the
        // Koneksi Printer page. Never fall back to legacy pos_printer here.
        $rows = $this->Pos_print_model->agent_devices($agentName);
        if (empty($rows)) {
            $this->bootstrap_error('Tidak ada Koneksi Printer aktif untuk agent ini. Periksa nama agent, MAC, port, dan status koneksinya.');
            return;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'success',
                'message' => 'Printer bootstrap loaded.',
                'config_source' => 'pos_print_connection',
                // Range (rather than one exact version) keeps an already
                // installed agent compatible through a non-breaking release.
                'agent_contract' => [
                    'min_protocol' => 1,
                    'max_protocol' => 2,
                ],
                'data' => $rows,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function bootstrap_error(string $message): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output
            ->set_status_header(422)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'error',
                'message' => $message,
                'config_source' => 'pos_print_connection',
                'data' => [],
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function verify_printer_agent_key(): bool
    {
        $agentName = $this->normalise_agent_name((string)$this->input->get('agent_name', true));
        $expectedKeys = $this->expected_printer_agent_keys($agentName);
        if (empty($expectedKeys)) {
            $this->output
                ->set_status_header(503)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Printer agent tidak tersedia.',
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            return false;
        }

        $providedKey = trim((string)$this->input->get_request_header('X-Printer-Key', true));
        $isValid = false;
        foreach ($expectedKeys as $expectedKey) {
            if ($providedKey !== '' && hash_equals($expectedKey, $providedKey)) {
                $isValid = true;
                break;
            }
        }
        if (!$isValid) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Akses printer agent ditolak.',
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            return false;
        }

        return true;
    }

    /**
     * Optional per-agent map for production pairing and staged rotation.
     *
     * POS_PRINTER_AGENT_KEYS is a private PHP-FPM environment JSON object:
     * {"KASIR-01":{"current":"new-secret","previous":"old-secret"}}
     * A simple string value is also accepted as current for compact deploys.
     * When the map is present it is authoritative: there is no global-key
     * fallback for an unknown agent. Existing installations keep using the
     * legacy current/previous global variables until the map is provisioned.
     */
    private function expected_printer_agent_keys(string $agentName): array
    {
        $configured = trim((string)getenv('POS_PRINTER_AGENT_KEYS'));
        if ($configured !== '') {
            $decoded = json_decode($configured, true);
            if (!is_array($decoded) || $agentName === '' || !array_key_exists($agentName, $decoded)) {
                return [];
            }
            $record = $decoded[$agentName];
            if (is_string($record)) {
                $record = ['current' => $record];
            }
            if (!is_array($record)) {
                return [];
            }
            return $this->non_empty_keys([
                $record['current'] ?? '',
                $record['previous'] ?? '',
            ]);
        }

        return $this->non_empty_keys([
            getenv('POS_PRINTER_BOOTSTRAP_KEY'),
            getenv('POS_PRINTER_BOOTSTRAP_KEY_PREVIOUS'),
        ]);
    }

    private function non_empty_keys(array $values): array
    {
        $keys = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $keys[] = $value;
            }
        }
        return array_values(array_unique($keys));
    }

    private function normalise_agent_name(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^[A-Z0-9][A-Z0-9_.-]{0,79}$/', $value) ? $value : '';
    }
}
