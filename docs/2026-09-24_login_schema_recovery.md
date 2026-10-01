# Login Recovery After Pull - 2026-09-24

> Historical recovery on the wrong application database. The owner subsequently
> confirmed **db_finance2** as canonical for both Finance and Member. Its login
> schema was already present and its 16 active accounts passed read-only checks.
> See [Canonical Database Cutover](2026-09-24_canonical_database_cutover.md).
> Do not switch back to db_finance based on this earlier recovery record.

## Cause

The active database was `db_finance`. It had no `auth_login_failure` table,
although the current Auth_model requires it before checking credentials.
The resulting persistence failure was intentionally rejected with the generic
maintenance message. Git pull updates source files, not the database schema.
`auth_session_log.login_at` also still used second-only DATETIME precision.

## Applied On This Server

Existing migrations executed against **db_finance**:

1. `sql/2026-09-03a_auth_login_throttle_foundation.sql`
2. `sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql`

The first creates the failure audit table, indexes, and nullable user foreign
key. The second preserves session history while aligning successful-login
timestamps with the microsecond failure-window boundary. The existing
delimiter-aware SQL parser executed the guarded procedure and removed it.

Backup/evidence: `/var/backups/finance-auth-recovery-20260924162130/` (root only).
The 1,337 existing session-log rows were retained. Account/password and role
assignment fingerprints matched before/after. No password resets, permission
changes, session invalidation, or authentication/throttle bypass was made.
No new SQL file or production authentication code change was required.

## Validation

- Live model checks inside a READ ONLY transaction passed for 16 active users:
  IP/account failure counters, permission resolution, division scope (6 GLOBAL,
  10 SINGLE), and advisory lock acquisition/release.
- Production HTTP login form and POST tested using a random nonexistent account.
  It produced the normal credential rejection, not the maintenance message.
  This generated one expected anonymous failed-login audit, not a logged-in session.
- Existing auth throttle, division scope, inactive-role permission, and POS
  mobile throttle regression suites passed.
- No real user's successful login was attempted without their credentials.

## Other Servers

Pulling this documentation does not apply the database repair elsewhere.
Check the active database and migration state before running these two existing
files. The second file requires a DELIMITER-aware client; the existing wrapper
`tools/db/apply_auth_session_log_login_at_microsecond.sh` supports that.
Do not disable login throttling or restore another server's account/session data.
