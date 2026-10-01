# Incoming Order Recovery - 2026-09-24

> Superseded database direction: the owner confirmed that **db_finance2** is
> canonical. Finance, Member, and wa-engine now use db_finance2. The nine new
> self orders below were migrated there, not the other way around. See
> [Canonical Database Cutover](2026-09-24_canonical_database_cutover.md).
> The following records the earlier investigation; do not use it to select
> db_finance or reapply its migrations to a different database blindly.

## Findings

- Member writes orders to `db_finance`. Finance previously selected `db_finance2`;
  commit `9b46c3f` had already restored its configuration to `db_finance` when
  this investigation began. This repair did not change that configuration.
- On September 24, `db_finance` contained nine SELF_ORDER orders and
  `db_finance2` contained none. The orders were not lost or awaiting creation
  by the notification worker. Verification remains in Finance Self Order.
- Four orders were paid but unverified: `MSO-20260924152706-E61A` (20,000),
  `MSO-20260924153313-4F61` (48,000), `MSO-20260924153457-9BFC` (74,000),
  and `MSO-20260924155423-A2DF` (87,000). Total: IDR 229,000.
- The active database lacked notification tables and the report scheduler's
  claim columns. The scheduler crashed with `Unknown column run_claim_token`
  before reaching the order notification worker.
- Saved SELF_ORDER/ONLINE_ORDER rules existed in `db_finance2`, with a cutoff
  of 5189. Copying that ID unchanged would suppress current orders in
  `db_finance`, whose latest order was 5115.
- Both incoming-order pages refreshed only on new IDs. Payment or verification
  changes to an existing order could therefore leave the displayed list stale.

## Source Changes

- Self Order and Online Food polling refresh on payment/verification state and
  count changes, not only new IDs. A failed list request is retried by the next
  poll even if the order state has not changed.
- The global notifier announces an existing ready-to-verify backlog and a
  payment transition to ready-to-verify, without repeating unchanged poll results.
- The layout cache version was bumped for the updated JavaScript.
- The report scheduler checks its claim columns before querying them. A missing
  claim migration no longer aborts the separate order-notification worker.
- No changes to order creation, payment callbacks, stock posting, verification
  permissions, or the member application's pending local edits.

## Server Recovery Executed

Existing migrations applied to **db_finance**, not a new SQL file:

1. `2026-09-23a_module_notifications.sql`
2. `2026-09-23b_module_notification_pdf_attachment.sql`
3. `2026-09-23a_add_logged_out_wa_session_status.sql`
4. `2026-09-02a_wa_report_schedule_claim_lease.sql`

Restored only the previously enabled WA SELF_ORDER and ONLINE_ORDER rules.
Group IDs and JIDs were checked against the active database: HOD NAMUA and
SUPERTEAM NAMUA. The cutoff was recalculated from the original rule timestamp
(`2026-09-23 23:26:57`) against active order creation dates, resulting in 5097.
Old delivery queue rows were not copied across database-local IDs. The original
rule timestamp was retained; the old database's user ID was not attributed to
an unrelated active-database user.

`wa-engine/.env` now selects `db_finance`. The engine was restarted as `www`
using existing session files and root-managed service credentials. No logout,
session deletion, QR relink, or personal-message safety-lock change occurred.
The existing writable `wa-engine-web.log` fallback is used for engine output.

Telegram was already disabled in the previous database and had no enabled
incoming-order rules. It was not enabled or assigned new recipients implicitly.
Unrelated procurement/report notification rules were not copied by this recovery.

Root-only evidence and configuration backup:
`/var/backups/finance-incoming-recovery-20260924161035/`.
This contains configuration material; do not publish or commit it.

## Verification

- Authenticated, read-only production HTTP requests: valid JSON/HTTP 200 for
  both channels, ALL and NEEDS_VERIFY filters. Self Order: 9 total, 8 ready to
  verify, 1 awaiting payment. Online Food: 0 for the selected date.
- Nine original orders compared before/after: no change to status, total,
  payment amount/timestamp, confirmation timestamp, or stock commit status.
- Existing minute worker resumed. All 18 self-order deliveries (9 orders x 2
  configured groups) reached SENT in the channel log. This is the engine's
  send result, not proof that a human recipient read the messages.
- Active-database WA session returned CONNECTED after restart, without QR reset.
- Offline Chrome test: 19 checks for both production views and global notifier,
  including same-ID QRIS payment, external verification, transient fetch failure,
  duplicate-alert prevention, and zero mutating browser requests.
- Existing smoke tests passed: Self Order UI (10), Online Food UI (10), incoming
  mobile scope (15), module notifications (20), report claim/lease (28), and
  CLI-only schedule runner (30). Changed PHP/JS syntax and diff checks passed.

## Deployment Notes

Push application/controller/view/JavaScript changes and regression tests.
Runtime `.env`, database changes, and service restart do not travel through Git.
Do not push live engine PID/log files or unrelated DDNS log changes.
On another server, validate its member/Finance/engine database alignment and
migration state first; do not copy this server's numeric order cutoffs or queue.
Paid orders still require authorized operator verification in Self Order.
