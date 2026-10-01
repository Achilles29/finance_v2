<?php
declare(strict_types=1);

// Reuse the existing disposable socket-only MariaDB fixture, never deployment config.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--mysql-fixture', $argv, true)) {
    fwrite(STDERR, "Run with --mysql-fixture (isolated temporary MariaDB).\n");
    exit(2);
}

function finance_control_workspace_fixture($db, $context, $check, string $root): void
{
    require APPPATH . 'controllers/Whatsapp.php';
    $context->input = new class {
        public function ip_address(): string { return '127.0.0.1'; }
    };
    $context->load = new class {
        public function model($name): void {
            if ($name !== 'Purchase_model') throw new RuntimeException('Unexpected fixture model.');
        }
    };
    $http = (new ReflectionClass(Whatsapp::class))->newInstanceWithoutConstructor();
    $dispatch = new ReflectionMethod(Whatsapp::class, 'dispatchWaMutationCommand');
    $dispatch->setAccessible(true);
    $group = ['group_jid' => 'fixture@g.us', 'group_name' => 'Synthetic Finance'];
    $sequence = 0;
    $send = static function (string $command, array $override = []) use ($http, $dispatch, $group, &$sequence): string {
        $context = array_replace([
            'sender_jid' => '100@lid', 'sender_is_admin' => true,
            'message_id' => 'FIXTURE_' . ++$sequence, 'message_timestamp' => time(),
        ], $override);
        $normalized = preg_replace('/^[!\/.#]+/', '', strtolower($command));
        $normalized = preg_replace('/^input\s+/', '', $normalized);
        return $dispatch->invoke($http, $command, $normalized, $group, $context);
    };
    foreach ([[1, 'TUNAI', 'Kas Tunai', 1000000], [2, 'MANDIRI', 'Bank Mandiri', 0], [3, 'LAMA', 'Tidak Aktif', 0]] as [$id, $code, $name, $balance]) {
        $check($db->insert('fin_company_account', ['id' => $id, 'account_code' => $code, 'account_name' => $name, 'current_balance' => $balance, 'is_active' => $id === 3 ? 0 : 1]), 'fixture account ' . $code);
    }
    $balance = static fn(int $id = 1): float => (float)$db->get_where('fin_company_account', ['id' => $id])->row('current_balance');
    $count = static fn(): int => $db->count_all('fin_account_mutation_log');
    $in = 'mutasi in TUNAI 50.000,25 Setoran Owner kategori:OWNER_CAPITAL';
    $reply = $send($in, ['message_id' => 'IN_1']);
    $check(str_contains($reply, 'berhasil diposting') && $balance() === 1050000.25 && $count() === 1, 'WA IN posts correct amount/category: ' . $reply);
    $row = $db->get('fin_account_mutation_log')->row_array();
    $check($row['report_category'] === 'OWNER_CAPITAL' && str_contains($row['notes'], '100@lid; pesan IN_1') && str_contains($row['notes'], 'Setoran Owner'), 'audit preserves sender/message ID and original note case');
    $check(str_contains($send($in, ['message_id' => 'IN_1']), 'sudah diposting') && $count() === 1, 'replayed IN never credits twice');
    $check(str_contains($send(str_replace('TUNAI', 'MANDIRI', $in), ['message_id' => 'IN_1']), 'sudah diposting') && $balance(2) === 0.0, 'same message cannot switch accounts on replay');

    $reply = $send('/mutasi out rekening:TUNAI nominal:25.000 catatan:Beli Bensin kategori:OPERATING_EXPENSE ref:FIXTURE-OUT');
    $check(str_contains($reply, 'berhasil diposting') && $balance() === 1025000.25, 'key-value OUT posts through Finance model');
    $reply = $send('!input mutasi transfer Kas Tunai Bank Mandiri 100000 Setor Bank', ['message_id' => 'TRANSFER_1']);
    $check(str_contains($reply, 'berhasil diposting') && $balance() === 925000.25 && $balance(2) === 100000.0 && $count() === 4, 'named-account TRANSFER creates balanced pair: ' . $reply);
    $check(str_contains($send('mutasi transfer TUNAI MANDIRI 100000 Setor Bank', ['message_id' => 'TRANSFER_1']), 'sudah diposting') && $count() === 4, 'replayed transfer never creates extra pair');

    $before = [$balance(), $balance(2), $count()];
    foreach ([
        [$in, ['sender_is_admin' => false], 'admin grup'],
        [$in, ['sender_is_admin' => 'true'], 'admin grup'],
        [$in, ['sender_jid' => ''], 'admin grup'],
        [$in, ['message_id' => ''], 'Identitas/waktu'],
        [$in, ['message_timestamp' => time() - 901], 'kedaluwarsa'],
        ['mutasi in TUNAI 100 modal', [], 'Kategori laporan'],
        ['mutasi in TUNAI 100 modal kategori:OPERATING_EXPENSE', [], 'Kategori laporan'],
        ['mutasi out TUNAI 100 biaya kategori:PLATFORM_FEE', [], 'settlement'],
        ['mutasi in TUNAI -100 modal kategori:OWNER_CAPITAL', [], 'Nominal'],
        ['mutasi in TUNAI 100k modal kategori:OWNER_CAPITAL', [], 'Nominal'],
        ['mutasi in TUNAI 1.2.3 modal kategori:OWNER_CAPITAL', [], 'Nominal'],
        ['mutasi in TUNAI 100 kategori:OWNER_CAPITAL', [], 'Catatan'],
        ['mutasi in LAMA 100 modal kategori:OWNER_CAPITAL', [], 'Rekening'],
        ['mutasi in TUNAI 100 modal 2026-02-30 kategori:OWNER_CAPITAL', [], 'Tanggal'],
        ['mutasi transfer TUNAI TUNAI 100 setor', [], 'berbeda'],
        ['mutasi transfer TUNAI MANDIRI 999999999 setor', [], 'tidak cukup'],
    ] as [$command, $override, $expected]) {
        $reply = $send($command, $override);
        $check(str_contains($reply, $expected) && [$balance(), $balance(2), $count()] === $before, 'reject without posting: ' . $expected . ' / ' . $command);
    }
    $today = date('Y-m-d');
    $db->insert('fin_period_close', ['period_code' => 'FIXTURE', 'period_year' => (int)date('Y'), 'period_start' => $today, 'period_end' => $today, 'status' => 'CLOSED']);
    $check(str_contains($send($in), 'CLOSED') && $count() === 4, 'closed finance period stays protected');
    $db->where('period_code', 'FIXTURE')->update('fin_period_close', ['status' => 'OPEN']);

    $payload = ['account_id' => 1, 'to_account_id' => 2, 'mutation_type' => 'TRANSFER', 'amount' => 10, 'mutation_date' => $today, 'notes' => 'Model retry fixture', 'client_request_key' => str_repeat('f', 32)];
    $model = $context->Purchase_model;
    $check($model->apply_manual_account_mutation($payload, 0)['ok'], 'keyed transfer supported by model');
    $before = [$balance(), $balance(2), $count()];
    $check(!empty($model->apply_manual_account_mutation($payload, 0)['replayed']) && [$balance(), $balance(2), $count()] === $before, 'model transfer retry preserves both balances');
    $check(!$model->apply_manual_account_mutation(array_replace($payload, ['amount' => 11]), 0)['ok'], 'model transfer rejects changed payload');
    unset($payload['client_request_key']);
    $check($model->apply_manual_account_mutation($payload, 0)['ok'], 'existing web transfer without request key still works');
    echo "PASS: WA mutation input integration; disposable DB only, no real balances or messages.\n";
}

require __DIR__ . '/finance_mutation_reporting_smoke.php';
