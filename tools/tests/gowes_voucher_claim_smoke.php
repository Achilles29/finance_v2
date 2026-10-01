<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--mysql-fixture', $argv, true)) { fwrite(STDERR, "Use --mysql-fixture; no application DB is accessed.\n"); exit(2); }

function finance_control_workspace_fixture($db, $context, $check, string $root): void
{
    $ddl = file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
    foreach (['pos_voucher_campaign', 'pos_voucher_issue', 'pos_voucher_redemption', 'pos_voucher_usage'] as $table) {
        preg_match('/CREATE TABLE `' . $table . '` \(.*?;\n/s', $ddl, $m);
        $check($db->query($m[0]) !== false, 'voucher fixture table ' . $table);
    }
    $sql = preg_replace('/^--.*$/m', '', file_get_contents($root . '/sql/2026-09-30a_gowes_voucher_claim.sql'));
    for ($round = 0; $round < 2; $round++) foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $check($db->query($statement) !== false, 'GOWES migration is rerunnable');
    $check($db->count_all('pos_voucher_campaign') === 1, 'one issue-only campaign, no duplicate seed');
    require APPPATH . 'models/Gowes_voucher_model.php';
    require APPPATH . 'models/Pos_model.php';
    $model = new Gowes_voucher_model();
    $prepared = Gowes_participant_import::prepare([
        ['email' => ' RIDER@example.invalid ', 'nama_lengkap' => 'First Rider'],
        ['email' => 'rider@EXAMPLE.invalid', 'nama_lengkap' => 'Duplicate Rider'],
        ['email' => 'rider2@example.invalid', 'nama_lengkap' => '<script>name</script>'],
        ['email' => 'not an email'], ['email' => ''], ['email' => 'rider3@example.invalid'],
    ]);
    $check(count($prepared['rows']) === 3 && $prepared['duplicate_rows'] === 1 && $prepared['invalid_rows'] === 2, 'case/space normalization, first duplicate retained, invalid rows skipped');
    $check($prepared['rows'][0]['participant_name'] === 'First Rider', 'first record is authoritative within one import');
    $import = $model->import_participants($prepared, 'fixture.xlsx', str_repeat('a', 64));
    $check($import['ok'] && $import['summary']['added_rows'] === 3, 'participants imported without issuing vouchers');
    $check($db->count_all('pos_voucher_issue') === 0, 'no voucher before a claim');
    $before = time();
    $claim = $model->claim('RIDER@EXAMPLE.INVALID');
    $check($claim['ok'] && !$claim['existing'] && $claim['voucher']['percent'] === 15.0, 'registered email receives a real 15 percent voucher');
    $check(preg_match('/^[A-HJ-NP-Z2-9]{8}$/D', $claim['voucher']['code']) === 1, 'new code is exactly eight unambiguous characters without separators');
    $row = $db->get('pos_voucher_issue')->row_array();
    $check(strtotime($row['expired_at']) - strtotime($row['issued_at']) === 7 * 86400 && strtotime($row['issued_at']) >= $before, 'expiry exactly seven days from actual claim');
    $check($row['min_spend_amount'] == 0 && $row['member_id'] === null && $row['voucher_status'] === 'OPEN', 'no minimum, no member creation requirement, initially OPEN');
    $retry = $model->claim(' rider@example.invalid ');
    $check($retry['ok'] && $retry['existing'] && $retry['voucher'] === $claim['voucher'] && $db->count_all('pos_voucher_issue') === 1, 'repeat email gets same code and expiry');
    $collisionModel = new class extends Gowes_voucher_model {
        public string $collisionCode = '';
        public int $attempts = 0;
        protected function generate_voucher_code(): string
        {
            return $this->attempts++ === 0 ? $this->collisionCode : parent::generate_voucher_code();
        }
    };
    $collisionModel->collisionCode = $claim['voucher']['code'];
    $second = $collisionModel->claim('rider2@example.invalid');
    $check($second['ok'] && $collisionModel->attempts === 2, 'an occupied short code is retried without changing the original voucher');
    $check($second['ok'] && $second['voucher']['code'] !== $claim['voucher']['code'], 'each participant receives a different unguessable code');
    $check(!$model->claim('missing@example.invalid')['ok'] && !$model->claim('malformed')['ok'] && $db->count_all('pos_voucher_issue') === 2, 'unknown/invalid email cannot create a voucher');
    $update = Gowes_participant_import::prepare([['email' => 'RIDER@example.invalid', 'nama_lengkap' => 'Updated Rider'], ['email' => 'new@example.invalid', 'nama_lengkap' => 'New Rider']]);
    $check($model->import_participants($update, 'updated.xlsx', str_repeat('b', 64))['ok'], 'updated roster imported');
    $check($db->count_all('evt_gowes_participant') === 4 && $model->claim('rider@example.invalid')['voucher'] === $claim['voucher'], 'reimport preserves claims, old omitted participants, original expiry');
    $campaign = $db->get('pos_voucher_campaign')->row_array();
    $check($campaign['issue_mode'] === 'MEMBER_TARGETED', 'campaign cannot be applied as reusable public promo');
    $pos = (new ReflectionClass(Pos_model::class))->newInstanceWithoutConstructor();
    $lookup = new ReflectionMethod(Pos_model::class, 'cashier_exact_issued_voucher'); $lookup->setAccessible(true);
    $option = $lookup->invoke($pos, strtolower($claim['voucher']['code']), ['header' => [], 'lines' => []], 100.0);
    $check($option && $option['kind'] === 'ISSUE' && $option['valid'] && $option['estimated_discount'] === 15.0, 'cashier finds claimed voucher by full code without a member');
    $check($lookup->invoke($pos, substr($claim['voucher']['code'], 0, 3), ['header' => [], 'lines' => []], 100.0) === null, 'partial search does not enumerate participant voucher codes');
    $check($lookup->invoke($pos, Gowes_voucher_model::CAMPAIGN_CODE, ['header' => [], 'lines' => []], 100.0) === null, 'campaign code cannot replace a unique issued voucher code');
    $evaluate = new ReflectionMethod(Pos_model::class, 'evaluate_cashier_voucher_discount'); $evaluate->setAccessible(true);
    foreach ([1, 100, 100000, 10000000] as $amount) {
        $discount = $evaluate->invoke($pos, $campaign, ['lines' => []], (float)$amount, $row);
        $check($discount['ok'] && $discount['discount_amount'] === round($amount * .15, 2), 'existing POS calculates 15 percent at base ' . $amount . ' without min/cap');
    }
    $db->where('id', $row['id'])->update('pos_voucher_issue', ['voucher_status' => 'REDEEMED']);
    $used = $model->claim('rider@example.invalid');
    $check($used['ok'] && $used['existing'] && $used['voucher']['status'] === 'REDEEMED' && $db->count_all('pos_voucher_issue') === 2, 'used voucher stays used on repeat claim');
    $check($lookup->invoke($pos, $claim['voucher']['code'], ['header' => [], 'lines' => []], 100.0) === null, 'used voucher absent from cashier search');
    $db->where('id', $row['id'])->update('pos_voucher_issue', ['voucher_status' => 'OPEN', 'expired_at' => '2020-01-01 00:00:00']);
    $check($model->claim('rider@example.invalid')['voucher']['status'] === 'EXPIRED' && $db->count_all('pos_voucher_issue') === 2, 'expiry is not extended on repeat claim');
    $check($lookup->invoke($pos, $claim['voucher']['code'], ['header' => [], 'lines' => []], 100.0) === null, 'expired voucher absent from cashier search');
    $db->query("CREATE TRIGGER fixture_claim_failure BEFORE UPDATE ON evt_gowes_participant FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture failure'");
    $check(!$model->claim('rider3@example.invalid')['ok'] && $db->count_all('pos_voucher_issue') === 2, 'link failure rolls back newly issued voucher');
    $db->query('DROP TRIGGER fixture_claim_failure');
    $db->where('campaign_code', Gowes_voucher_model::CAMPAIGN_CODE)->update('pos_voucher_campaign', ['is_active' => 0]);
    $check(!$model->claim('rider3@example.invalid')['ok'], 'inactive campaign rejects new claims');
    $db->where('campaign_code', Gowes_voucher_model::CAMPAIGN_CODE)->update('pos_voucher_campaign', ['is_active' => 1]);
    $check($model->claim('rider3@example.invalid')['ok'], 'valid retry after rollback creates one voucher');

    $redeem = new ReflectionMethod(Pos_model::class, 'apply_cashier_voucher_redemption'); $redeem->setAccessible(true);
    $secondRow = $db->get_where('pos_voucher_issue', ['voucher_code' => $second['voucher']['code']])->row_array();
    $payload = ['voucher_issue_id' => (int)$secondRow['id'], 'campaign_id' => (int)$campaign['id'], 'voucher_code' => $secondRow['voucher_code'],
        'voucher_kind' => 'ISSUE', 'redeem_amount' => 15, 'face_value_percent' => 15];
    $db->trans_begin();
    $redeem->invoke($pos, $payload, 11, 21, 0, date('Y-m-d H:i:s'));
    $check($db->trans_status() && $db->trans_commit(), 'existing POS redeems the issued event voucher');
    $db->trans_begin();
    try { $redeem->invoke($pos, $payload, 12, 22, 0, date('Y-m-d H:i:s')); $denied = false; }
    catch (RuntimeException $e) { $denied = str_contains($e->getMessage(), 'sudah digunakan'); }
    $db->trans_rollback();
    $check($denied && $db->count_all('pos_voucher_redemption') === 1, 'a second payment cannot consume the same voucher after a stale preview');
    $db->trans_begin();
    $redeem->invoke($pos, $payload, 11, 21, 0, date('Y-m-d H:i:s'));
    $db->trans_commit();
    $check($db->count_all('pos_voucher_redemption') === 1 && $db->count_all('pos_voucher_usage') === 1, 'same payment retry retains one redemption and one usage record');

    $third = $model->claim('rider3@example.invalid');
    $legacyCode = 'GW9-TEST-0000-0000-0001';
    $db->where('voucher_code', $third['voucher']['code'])->update('pos_voucher_issue', ['voucher_code' => $legacyCode]);
    $legacy = $model->claim('rider3@example.invalid');
    $check($legacy['ok'] && $legacy['existing'] && $legacy['voucher']['code'] === $legacyCode
        && $legacy['voucher']['expires_at'] === $third['voucher']['expires_at'] && $db->count_all('pos_voucher_issue') === 3,
        'previously issued long codes and expiry are preserved without reissuing');
    $check($lookup->invoke($pos, $legacyCode, ['header' => [], 'lines' => []], 100.0) !== null, 'legacy long codes remain usable in POS');

    require APPPATH . 'libraries/Gowes_claim_guard.php';
    $directory = sys_get_temp_dir() . '/gowes-guard-test-' . bin2hex(random_bytes(6));
    $guard = new Gowes_claim_guard(['directory' => $directory]);
    for ($i = 0; $i < 12; $i++) $check($guard->attempt('192.0.2.1', 'fixture-browser')['ok'], 'guard allowed attempt ' . $i);
    $denied = $guard->attempt('192.0.2.1', 'fixture-browser');
    $check(!$denied['ok'] && $denied['status'] === 429, 'thirteenth attempt is throttled across workers');
    $check(!(new Gowes_claim_guard(['directory' => $directory]))->attempt('192.0.2.1', 'fixture-browser')['ok'], 'limit persists in a separate guard instance');
    $check(!str_contains(file_get_contents($directory . '/buckets.php'), '192.0.2.1'), 'rate limiter does not persist plaintext IP');
    unlink($directory . '/buckets.php'); rmdir($directory);
    echo "PASS: GOWES claim, import, expiry, rollback, uniqueness, POS discount, and rate limit. No production database accessed.\n";
}
require __DIR__ . '/finance_mutation_reporting_smoke.php';
