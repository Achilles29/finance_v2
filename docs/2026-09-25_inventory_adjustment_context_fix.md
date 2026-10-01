# Daily Recon, Daily Matrix, and Adjustment

## Database and Scope

- The user confirmed `db_finance` is the current database. Connection settings were not changed.
- No schema migration, stock correction, real adjustment posting, or historical rebuild was executed.
- Live verification used a read-only transaction and the existing controller/model read methods.

## Findings and Fixes

1. Division Daily Recon posted the list filter (`EVENT` or `REGULER`) as a stock destination. It now posts the selected row's exact destination, such as `KITCHEN_EVENT`. The server rejects aggregate destinations rather than guessing another profile.
2. Both Daily Matrices lacked the scoped adjustment CSRF token and used obsolete posting shortcuts. They now save a DRAFT with CSRF protection and open that document in Adjustment. Posting still requires the existing document-bound password verification. Saving a draft does not update stock.
3. Adjustment could retain a selected profile after its destination changed. Context changes now invalidate selections and pending lookup results. Division/destination are locked while unsaved lines exist; removing all lines unlocks them.
4. Adjustment's month and exact-destination filters disabled SQL value escaping. A month such as `2026-09` was interpreted as subtraction, while destination strings were unquoted. Both Per Nota and Per Rincian now use escaped values.

## Verification

At the time of the read-only check on 2026-09-25:

- KECAP MANIS, division KITCHEN, brand BUAH SIWALAN: `KITCHEN_EVENT` was 1,500 ML; `KITCHEN` was 2,906.6667 ML. Both snapshots resolved to their own monthly row.
- September adjustment month filtering returned 173 division documents and 2 warehouse documents, matching direct read-only counts. Detail counts and destination/status filters also matched.
- 38 live read checks passed. No stock-writing method was invoked.
- New regression suites passed: destination payload (17), snapshot validation (21), matrix/draft workflow (11), and actual CI3 SQL compilation (26).
- Existing step-up, mutation CSRF, inventory period guard, atomic barrier, and component Daily Recon step-up suites passed.
- Changed PHP files passed lint; seven view variants rendered and their inline JavaScript parsed successfully using fixtures. `git diff --check` passed.

## Operator Flow

- Reload Daily Recon before retrying KECAP MANIS EVENT.
- From a Daily Matrix, use **Simpan & Lanjut Verifikasi**. The saved draft opens in Adjustment; choose **Post** and complete password verification.
- No SQL file needs to be executed for this update. Tests intentionally do not post a real adjustment; the operator must verify the physical quantity before posting.
