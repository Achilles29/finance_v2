# Upload storage recovery - 24 September 2026

## Findings

- The active document root is `/www/wwwroot/finance`; both Nginx and PHP-FPM
  run as `www` on this server.
- `uploads/` contained only `.gitkeep`. The six configured upload directories
  below it were missing, not merely unreadable.
- The business-profile and POS-printer logo directories belonged to
  `www-data:www-data` with mode 0755; the PHP-FPM account could not write them.
- A request for `uploads/coffee-labels/prau-red-wine-wine.png` returned HTTP 404.
- Read-only database checks found 266 distinct upload references. 265 had
  matching files in previous application copies; one artwork was unavailable:
  `uploads/coffee-labels/flores-01-fully-washed.png` (label ID 27).

## Recovery

- Restored 341 media files, including gallery images, from `finance_bak2`,
  `core_bak`, and `pos`. Identical relative paths were compared by SHA-256;
  no conflicting versions were found. Existing destination files were preserved.
- Restored media types were verified; hidden probes and executable files were
  not copied. No symlinks were followed.
- Upload directories use owner/group `www:www`, directories 0750 and restored
  files 0640. The two writable logo directories were corrected separately.
  Source-code directories, private financial evidence, and sessions were not
  made writable or publicly accessible.
- Permission-before records, source paths, and copy hashes are in
  `/var/backups/finance-upload-recovery-20260924025221/manifest.jsonl` (root-only).
- No database writes, SQL migrations, or webserver changes were performed.
- Removed automatic chmod 0777 from Roastery, Assets, and product uploads;
  label SVG upload no longer grants world-write access.

## Validation

- All eight policy upload directories report READY when checked as `www`.
- Unique temporary probes passed write/read/remove checks in all eight paths.
- 361 files under `uploads/` and `assets/uploads/` were readable as `www`.
- All 356 media URLs tested directly against the active HTTPS virtual host
  returned HTTP 200. This checks origin delivery, not a logged-in browser UI
  or any external CDN cache.
- PHP upload temp storage and private WA PDF storage are writable by `www`.
- `flores-01-fully-washed.png` still requires the original file to be uploaded;
  no unrelated artwork was substituted and its database reference was retained.

## Deploying elsewhere

Pulling code does not transfer upload contents (`uploads/*` is gitignored),
and Git does not deploy ownership or ordinary read/write permission changes.
Preserve/copy the correct server's upload media separately, without overwriting
newer files or mixing customer data. Determine that server's PHP-FPM and Nginx
accounts before setting permissions; `www` and 0750/0640 apply to this server.

```bash
runuser -u www -- /www/server/php/81/bin/php tools/install/upload_storage.php check
runuser -u www -- /www/server/php/81/bin/php tools/tests/c3_upload_storage_smoke.php
runuser -u www -- /www/server/php/81/bin/php tools/tests/upload_controller_permissions_smoke.php
```

Do not use recursive chmod 777 on the project. The existing `prepare` command
creates missing directories only; it does not restore missing media or fix
ownership. Upload files and database backups must be retained together during
application moves.
