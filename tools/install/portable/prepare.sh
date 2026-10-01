#!/bin/sh
# One public entry point. No install/download/chmod/cron mutation before PHP's explicit confirmation.
set -eu
finance_script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd -P)
finance_php=''
for finance_candidate in "$(command -v php 2>/dev/null || true)" /usr/bin/php8.1 /usr/local/bin/php /opt/php81/bin/php /www/server/php/81/bin/php; do
  if [ -n "$finance_candidate" ] && [ -x "$finance_candidate" ] && "$finance_candidate" -r 'exit(PHP_VERSION_ID>=80100 && PHP_VERSION_ID<80200 && PHP_INT_SIZE===8 ? 0 : 1);' >/dev/null 2>&1; then
    finance_php=$finance_candidate
    break
  fi
done
if [ -z "$finance_php" ]; then
  echo 'PHP CLI 8.1 (64 bit) belum ditemukan. Minta admin menyediakan runtime yang sesuai tanpa mengganti PHP website lain.' >&2
  echo 'Jika tersedia pada lokasi khusus, jalankan PHP tersebut dengan tools/install/portable/prepare.php.' >&2
  exit 1
fi
exec "$finance_php" "$finance_script_dir/prepare.php" "$@"
