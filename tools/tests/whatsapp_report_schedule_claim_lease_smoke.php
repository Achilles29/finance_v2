<?php

declare(strict_types=1);

/** Batch 47 smoke: no CI bootstrap, real DB, network, Bot, config, or logs. */
final class WaClaimLeaseDbDouble
{
    public array $row;
    public int $botCalls = 0;

    public function __construct(array $row)
    {
        $this->row = $row;
    }

    public function due(DateTimeImmutable $now): bool
    {
        $cutoff = $now->modify('-10 minutes')->format('Y-m-d H:i:s');
        return (int)$this->row['is_active'] === 1
            && (string)$this->row['send_time'] <= $now->format('H:i:s')
            && ($this->row['last_sent_date'] === null || $this->row['last_sent_date'] < $now->format('Y-m-d'))
            && ($this->row['last_run_at'] === null || $this->row['last_run_at'] < $cutoff)
            && ($this->row['run_claim_token'] === null
                || $this->row['run_claim_token'] === ''
                || $this->row['run_claimed_at'] === null
                || $this->row['run_claimed_at'] < $cutoff);
    }

    public function claim(string $token, DateTimeImmutable $now): bool
    {
        if (!$this->due($now)) {
            return false;
        }
        $this->row['run_claim_token'] = $token;
        $this->row['run_claimed_at'] = $now->format('Y-m-d H:i:s');
        return true; // affected_rows === 1
    }

    public function sendIfOwner(bool $claimed): void
    {
        if ($claimed) {
            $this->botCalls++;
        }
    }

    public function finalize(string $token, bool $ok, DateTimeImmutable $now): bool
    {
        if ($this->row['run_claim_token'] !== $token) {
            return false; // affected_rows === 0: stale/wrong owner
        }
        $this->row['last_run_at'] = $now->format('Y-m-d H:i:s');
        $this->row['last_status'] = $ok ? 'SENT' : 'FAILED';
        $this->row['last_error'] = $ok ? null : 'simulated failure';
        if ($ok) {
            $this->row['last_sent_at'] = $now->format('Y-m-d H:i:s');
            $this->row['last_sent_date'] = $now->format('Y-m-d');
        }
        $this->row['run_claim_token'] = null;
        $this->row['run_claimed_at'] = null;
        return true;
    }
}

$checks = 0;
$failures = [];
function waLeaseAssert(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function waLeaseMethod(string $source, string $name): string
{
    $pattern = '/\n    (?:public|protected|private) function ' . preg_quote($name, '/') . '\s*\(/';
    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }
    $start = (int)$match[0][1];
    $offset = $start + strlen($match[0][0]);
    if (preg_match('/\n    (?:public|protected|private) function /', $source, $next, PREG_OFFSET_CAPTURE, $offset) !== 1) {
        return substr($source, $start);
    }
    return substr($source, $start, (int)$next[0][1] - $start);
}

$now = new DateTimeImmutable('2026-09-02 12:30:00');
$base = [
    'id' => 47, 'is_active' => 1, 'send_time' => '12:00:00',
    'last_run_at' => null, 'last_sent_at' => null, 'last_sent_date' => null,
    'last_status' => null, 'last_error' => null,
    'run_claim_token' => null, 'run_claimed_at' => null,
];

// Two runners selected the same candidate before either atomic claim UPDATE.
$race = new WaClaimLeaseDbDouble($base);
$selectedA = $race->due($now);
$selectedB = $race->due($now);
$claimedA = $selectedA && $race->claim(str_repeat('a', 32), $now);
$claimedB = $selectedB && $race->claim(str_repeat('b', 32), $now);
$race->sendIfOwner($claimedA);
$race->sendIfOwner($claimedB);
waLeaseAssert($claimedA && !$claimedB && $race->botCalls === 1, 'two runners: one claim and one send');
waLeaseAssert(!$race->claim(str_repeat('c', 32), $now->modify('+9 minutes')), 'active lease skips');
waLeaseAssert($race->claim(str_repeat('b', 32), $now->modify('+10 minutes +1 second')), 'expired lease reclaims');

$beforeStale = $race->row;
waLeaseAssert(
    !$race->finalize(str_repeat('a', 32), true, $now->modify('+10 minutes +2 seconds'))
        && $race->row === $beforeStale,
    'stale finalizer cannot overwrite new owner'
);
waLeaseAssert(
    $race->finalize(str_repeat('b', 32), true, $now->modify('+10 minutes +3 seconds'))
        && $race->row['last_status'] === 'SENT'
        && $race->row['run_claim_token'] === null
        && $race->row['run_claimed_at'] === null,
    'owner success clears lease'
);

$failed = new WaClaimLeaseDbDouble($base);
$failureToken = str_repeat('d', 32);
waLeaseAssert($failed->claim($failureToken, $now), 'failure path claims');
$beforeWrongOwner = $failed->row;
waLeaseAssert(
    !$failed->finalize(str_repeat('e', 32), false, $now->modify('+1 minute'))
        && $failed->row === $beforeWrongOwner,
    'wrong owner failure cannot finalize'
);
waLeaseAssert(
    $failed->finalize($failureToken, false, $now->modify('+1 minute'))
        && $failed->row['last_status'] === 'FAILED'
        && $failed->row['run_claim_token'] === null
        && $failed->row['run_claimed_at'] === null,
    'owner failure clears lease'
);
waLeaseAssert(!$failed->due($now->modify('+10 minutes')), 'failure retry waits ten minutes');
waLeaseAssert($failed->due($now->modify('+11 minutes +1 second')), 'failure retries after ten minutes');

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Whatsapp.php');
$runner = waLeaseMethod($controller, 'runDueWaReportSchedules');
$claim = waLeaseMethod($controller, 'claimDueWaReportSchedule');
$send = waLeaseMethod($controller, 'sendWaReportSchedule');
$markFailed = waLeaseMethod($controller, 'markWaReportScheduleFailed');
$manual = waLeaseMethod($controller, 'report_schedules');
$cli = waLeaseMethod($controller, 'api_schedule_run');

waLeaseAssert(
    strpos($runner, "field_exists(\$column, 'wa_report_schedule')") !== false
        && strpos($runner, "field_exists(\$column, 'wa_report_schedule')") < strpos($runner, "->from('wa_report_schedule')")
        && strpos($runner, '2026-09-02a_wa_report_schedule_claim_lease.sql') !== false,
    'missing claim migration returns before SQL so module order notifications can continue'
);

waLeaseAssert(strpos($claim, 'bin2hex(random_bytes(16))') !== false, 'production uses random 16-byte token');
waLeaseAssert(strpos($claim, '$this->db->affected_rows() === 1') !== false, 'production requires exactly one claimed row');
foreach (['is_active', 'send_time <=', 'last_sent_date', 'last_run_at', 'run_claim_token', 'run_claimed_at'] as $guard) {
    waLeaseAssert(strpos($claim, $guard) !== false, 'conditional claim guard: ' . $guard);
}
waLeaseAssert(
    strpos($runner, "strtotime('-10 minutes')") !== false
        && strpos($runner, 'claimDueWaReportSchedule(') !== false
        && strpos($runner, 'sendWaReportSchedule((int)$row[\'id\'], false, $row, $claimToken)') !== false,
    'runner sends only after claim with ten-minute lease'
);
foreach ([$send, $markFailed] as $finalizer) {
    waLeaseAssert(
        strpos($finalizer, "where('run_claim_token', \$claimToken)") !== false
            && strpos($finalizer, "['run_claim_token'] = null") !== false
            && strpos($finalizer, "['run_claimed_at'] = null") !== false,
        'production finalizer is owner-only and clears lease'
    );
}
waLeaseAssert(strpos($manual, '$this->sendWaReportSchedule($id, true);') !== false, 'manual send_now remains claim-free');
waLeaseAssert(
    strpos($cli, '$this->input->is_cli_request()') !== false
        && substr_count($cli, '$this->runDueWaReportSchedules()') === 1,
    'Batch 46 CLI path remains intact'
);

$sqlFiles = [
    $root . '/sql/2026-08-15b_wa_report_schedule.sql',
    $root . '/sql/2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql',
    $root . '/sql/2026-09-02a_wa_report_schedule_claim_lease.sql',
];
foreach ($sqlFiles as $sqlFile) {
    $sql = (string)file_get_contents($sqlFile);
    waLeaseAssert(
        preg_match('/run_claim_token\s+CHAR\(32\)\s+(?:DEFAULT\s+)?NULL/i', $sql) === 1
            && preg_match('/run_claimed_at\s+DATETIME\s+(?:DEFAULT\s+)?NULL/i', $sql) === 1,
        basename($sqlFile) . ' has consistent nullable lease columns'
    );
}
$migration = (string)file_get_contents($sqlFiles[2]);
waLeaseAssert(
    preg_match_all('/^\s*ADD COLUMN IF NOT EXISTS\s+run_claim(?:_token|ed_at)\b/mi', $migration) === 2
        && stripos($migration, 'setelah 2026-08-15b_wa_report_schedule.sql') !== false,
    'migration is idempotent and ordered after base table'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, '[FAIL] ' . count($failures) . ' of ' . $checks . ' checks failed.' . PHP_EOL);
    exit(1);
}

echo '[PASS] WhatsApp report schedule claim/lease: ' . $checks . ' checks; no DB/network/bootstrap/secret.' . PHP_EOL;
