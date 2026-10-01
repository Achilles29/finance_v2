<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--mysql-fixture', $argv, true)) { fwrite(STDERR, "Use --mysql-fixture; only an isolated database is used.\n"); exit(2); }

function finance_control_workspace_fixture($db, $context, $check, string $root): void
{
    $ddl = file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
    foreach (['crm_member', 'pos_order', 'pos_voucher_campaign', 'pos_voucher_issue', 'pos_voucher_redemption', 'pos_voucher_usage', 'pos_redeem_transaction'] as $table) {
        preg_match('/CREATE TABLE `' . $table . '` \(.*?;\n/s', $ddl, $match);
        $check($db->query($match[0]) !== false, 'fixture schema ' . $table);
    }
    $sql = preg_replace('/^--.*$/m', '', file_get_contents($root . '/sql/2026-09-30a_gowes_voucher_claim.sql'));
    foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $check($db->query($statement) !== false, 'GOWES fixture migration');
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $check((int)$db->query('SELECT @@foreign_key_checks AS enabled')->row_array()['enabled'] === 1, 'foreign keys enforced throughout deletion tests');
    $db->data_cache = [];
    require APPPATH . 'models/Loyalty_model.php';
    require APPPATH . 'models/Gowes_voucher_model.php';
    $loyalty = (new ReflectionClass(Loyalty_model::class))->newInstanceWithoutConstructor();
    $gowes = new Gowes_voucher_model();
    $campaign = $db->get('pos_voucher_campaign')->row_array();
    $create = static function (string $status = 'OPEN') use ($db, $campaign): int {
        $code = 'TEST-' . bin2hex(random_bytes(6));
        if (!$db->insert('pos_voucher_issue', ['voucher_issue_no' => $code, 'voucher_code' => $code,
            'campaign_id' => $campaign['id'], 'voucher_status' => $status])) throw new RuntimeException('Fixture voucher insert failed.');
        return (int)$db->insert_id();
    };
    $participant = static fn() => $db->get_where('evt_gowes_participant', ['email' => 'rider@example.invalid'])->row_array();
    $check($gowes->import_participants(Gowes_participant_import::prepare([
        ['email' => 'rider@example.invalid', 'nama_lengkap' => 'Fixture Rider'],
    ]), 'fixture.xlsx', str_repeat('a', 64))['ok'], 'synthetic participant imported');
    $first = $gowes->claim('rider@example.invalid');
    $check($first['ok'], 'synthetic claim created');
    $id = (int)$participant()['voucher_issue_id'];
    $blocked = $db->where('id', $id)->delete('pos_voucher_issue');
    $check($blocked === false && (int)$db->error()['code'] === 1451, 'reproduced original failure: participant foreign key blocks direct delete');
    $listed = $loyalty->voucher_issue_rows(['status' => 'ALL']);
    $check(count($listed['rows']) === 1 && (int)$listed['rows'][0]['is_gowes_claim'] === 1, 'listing flags linked GOWES vouchers for confirmation');
    $queryStart = count($db->queries);
    $deleted = $loyalty->delete_voucher_issue($id);
    $queries = implode("\n", array_slice($db->queries, $queryStart));
    $check($deleted['ok'] && $deleted['participant_claim_reset'], 'unused linked voucher can be deleted');
    $check(strpos($queries, 'SELECT id FROM evt_gowes_participant') < strpos($queries, 'SELECT * FROM pos_voucher_issue'), 'delete follows public claim lock order');
    $check($db->count_all('pos_voucher_issue') === 0 && $db->count_all('evt_gowes_participant') === 1, 'voucher removed but participant retained');
    $check($participant()['voucher_issue_id'] === null && $participant()['claimed_at'] === null && (int)$participant()['is_active'] === 1, 'claim reference and timestamp reset without changing eligibility');
    $second = $gowes->claim('rider@example.invalid');
    $check($second['ok'] && !$second['existing'] && $second['voucher']['code'] !== $first['voucher']['code'], 'participant can claim a new eight-character code after explicit deletion');
    $check($gowes->claim('rider@example.invalid')['voucher'] === $second['voucher'] && $db->count_all('pos_voucher_issue') === 1, 'new repeated claim remains idempotent');

    $id = (int)$participant()['voucher_issue_id'];
    $beforeParticipant = $participant();
    $beforeVoucher = $db->get_where('pos_voucher_issue', ['id' => $id])->row_array();
    $db->query("CREATE TRIGGER fixture_delete_failure BEFORE DELETE ON pos_voucher_issue FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture private SQL failure'");
    $db->db_debug = true;
    $failure = $loyalty->delete_voucher_issue($id);
    $check(!$failure['ok'] && strpos($failure['message'], 'dibatalkan') !== false && strpos($failure['message'], 'SQL') === false, 'SQL failure returns a safe structured error, not an HTML database error');
    $check($db->db_debug === true, 'database debug flag restored');
    $db->db_debug = false;
    $check($participant() === $beforeParticipant && $db->get_where('pos_voucher_issue', ['id' => $id])->row_array() === $beforeVoucher, 'failed deletion restores both claim link and voucher');
    $db->query('DROP TRIGGER fixture_delete_failure');

    $db->where('id', $id)->update('pos_voucher_issue', ['voucher_status' => 'REDEEMED']);
    $check(!$loyalty->delete_voucher_issue($id)['ok'] && $participant() === $beforeParticipant, 'redeemed voucher and participant link are preserved');
    $db->where('id', $id)->update('pos_voucher_issue', ['voucher_status' => 'OPEN', 'redeemed_at' => date('Y-m-d H:i:s')]);
    $check(!$loyalty->delete_voucher_issue($id)['ok'], 'redemption timestamp protects a voucher even with a stale OPEN status');
    $db->where('id', $id)->update('pos_voucher_issue', ['redeemed_at' => null]);
    $db->insert('crm_member', ['member_no' => 'FIXTURE', 'member_name' => 'Fixture Member']);
    $memberId = (int)$db->insert_id();
    foreach (['pos_voucher_redemption', 'pos_voucher_usage', 'pos_redeem_transaction'] as $table) {
        $history = ['voucher_issue_id' => $id];
        if ($table === 'pos_voucher_usage') $history['source_key'] = 'FIXTURE';
        if ($table === 'pos_redeem_transaction') $history += ['redeem_no' => 'FIXTURE', 'member_id' => $memberId, 'redeem_type' => 'VOUCHER'];
        $check($db->insert($table, $history), 'history fixture ' . $table);
        $check(!$loyalty->delete_voucher_issue($id)['ok'] && $db->count_all($table) === 1 && $participant() === $beforeParticipant,
            'protect history and claim link: ' . $table);
        $db->where('voucher_issue_id', $id)->delete($table);
    }

    $db->query('CREATE TABLE fixture_unknown_reference (id BIGINT UNSIGNED PRIMARY KEY, voucher_issue_id BIGINT UNSIGNED, FOREIGN KEY (voucher_issue_id) REFERENCES pos_voucher_issue(id)) ENGINE=InnoDB');
    $db->insert('fixture_unknown_reference', ['id' => 1, 'voucher_issue_id' => $id]);
    $failure = $loyalty->delete_voucher_issue($id);
    $check(!$failure['ok'] && strpos($failure['message'], 'terkait data lain') !== false && $participant() === $beforeParticipant, 'unknown reference gives a friendly error and rolls back claim reset');
    $db->query('DROP TABLE fixture_unknown_reference');
    foreach (['OPEN', 'EXPIRED', 'VOID'] as $status) {
        $plainId = $create($status);
        $result = $loyalty->delete_voucher_issue($plainId);
        $check($result['ok'] && !$result['participant_claim_reset'], 'ordinary unused ' . $status . ' voucher can still be deleted');
    }
    $check(!$loyalty->delete_voucher_issue(0)['ok'] && !$loyalty->delete_voucher_issue(999999)['ok'], 'invalid and missing IDs rejected');
    $check($loyalty->delete_voucher_issue($id)['ok'], 'retry after blocked history succeeds without orphan claims');
    $db->query('DROP TABLE evt_gowes_participant');
    $db->data_cache = [];
    $plainId = $create();
    $listed = $loyalty->voucher_issue_rows(['status' => 'ALL']);
    $check((int)$listed['rows'][0]['is_gowes_claim'] === 0 && $loyalty->delete_voucher_issue($plainId)['ok'], 'legacy installations without event table remain compatible');
    echo "PASS: voucher deletion, enforced foreign keys, rollback, repeat claim and history protection. No production data accessed.\n";
}
require __DIR__ . '/finance_mutation_reporting_smoke.php';
