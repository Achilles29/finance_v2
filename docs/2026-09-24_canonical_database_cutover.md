# Canonical Database Cutover - 2026-09-24

## Authoritative Database

The owner confirmed **db_finance2** is the production database. Finance had
real cashier transactions there, while Member created nine new self orders
in db_finance. Switching Finance to db_finance hid the real cashier data;
it did not delete that data. The earlier recovery documents are historical,
not instructions to select db_finance again.

Finance and Member now both select db_finance2. The WA engine environment and
its default database also select db_finance2. The engine was restarted using
the existing session and protected service credentials, without logout or QR
relink. Telegram and personal-message safety settings were not enabled.

## Executed Recovery

Both sites briefly returned HTTP 503 with Retry-After during the cutover.
All records were migrated in one database transaction after a full backup
of both databases. No schema migration, table creation, or stock posting was
needed for this cutover.

- Nine SELF_ORDER orders, 18 lines, three extras, nine payment headers, and
  nine payment lines were inserted into db_finance2.
- Four paid orders total **IDR 229,000**. Four existing POS cash mutations were
  copied with remapped payment-line references. Target account 6 (MIDTRANS)
  changed from 0 to 229,000, matching its mutation ledger.
- Seventeen source-only member registrations were preserved with their IDs.
  Member numbers, normalized phones, ID collisions, zero balances, and absent
  additional member ledger/address dependencies were checked before insertion.
- All 12 cashier orders dated September 24 were retained. The nine older
  cashier orders whose IDs collided with the source IDs were also unchanged.
- All nine migrated orders remain unverified, with stock status PENDING and
  no stock commits. Operators still verify them through Finance Self Order.
- Eighteen SENT notification records were copied with remapped source IDs and
  rebuilt delivery keys, preventing the migration from resending notifications.
- Source records remain in db_finance for audit, not as a second live database.

Internal order ID mapping (order numbers and external payment references remain
unchanged):

| Source ID | Destination ID | Order Number |
| --- | --- | --- |
| 5099 | 5233 | MSO-20260924152706-E61A |
| 5101 | 5235 | MSO-20260924152820-F471 |
| 5103 | 5237 | MSO-20260924152925-3B3D |
| 5105 | 5239 | MSO-20260924153039-58C0 |
| 5107 | 5241 | MSO-20260924153141-027B |
| 5109 | 5243 | MSO-20260924153313-4F61 |
| 5111 | 5245 | MSO-20260924153320-C117 |
| 5113 | 5247 | MSO-20260924153457-9BFC |
| 5115 | 5249 | MSO-20260924155423-A2DF |

## Payment And Session Compatibility

Member payment callbacks now find orders by the exact stored payment reference,
never by parsing the numeric prefix of a provider reference. Old numeric IDs
belong to different cashier orders in the canonical database. Callback handling
also checks signature, channel, provider, and amount; paid orders are not
downgraded by late callbacks. Persistence failures roll back and return HTTP 500.

Existing member sessions retain their member IDs. Existing QR links use an
ownership-scoped mapping in `/etc/finance-order-cutover-map.json`, outside the
web root, owned by root:www with mode 0640. The mapping requires the expected
database, logged-in member, destination ID, order number, and order channel.
QR actions operate on the resolved destination ID. Do not publish this file.

## Validation

- Full database dump completed successfully; gzip integrity check passed.
- Row-by-row order, detail, payment, member, and ledger comparisons passed.
  Existing target order headers were unchanged; no source rows were deleted.
- Read-only production Self Order API returned HTTP 200 with nine orders:
  eight NEEDS_VERIFY and one WAITING_PAYMENT (expired QR). Online Food returned
  zero orders for the date. Payment/verification statuses were not fabricated.
- Read-only Member model checks passed for all nine old/new links, nine
  wrong-member denials, and all five external payment references.
- Member login page returned HTTP 200. Read-only Finance authentication checks
  passed for 16 active accounts. No real-user password or session reset occurred.
- WA internal status returned CONNECTED, and db_finance2.wa_session heartbeat
  advanced after restart. Personal outbound remained disabled.
- `member/tools/tests/midtrans_reference_callback_smoke.php`: nine checks passed,
  with fake persistence and no network or real payment.
- PHP lint, Node syntax check, and both repositories' diff whitespace checks passed.

The live model test uses `/www/server/php/81/bin/php`, matching PHP-FPM. The
system PHP binary has a different local MySQL socket setting.

## Backup And Deployment

Private audit directory:
`/var/backups/finance-canonical-db2-20260924-093325/`.
It contains the complete dump of both databases, configuration snapshots,
source/target row snapshots, committed ID maps, and the migration scripts.
Never publish or commit this directory; it contains personal data and secrets.

Source changes exist in **both repositories**, Finance and Member. Push/pull
their code together and verify both select the intended canonical database.
The WA `.env` is server-local: set DB_NAME=db_finance2 and restart the existing
engine without clearing its auth files. Engine code defaults to db_finance2.

A Git pull does not copy migrated database records, runtime credentials, or the
private order-alias map to another server. Transfer those only for a deliberate
server migration that preserves these same IDs; do not replay this recovery on
another database. This recovery was executed once on this server and explicitly
refuses a second apply. Do not import db_finance over db_finance2.
