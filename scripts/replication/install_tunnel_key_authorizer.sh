#!/usr/bin/env bash
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "Jalankan sebagai root pada Server Utama." >&2
  exit 1
fi
if [[ $# -ne 1 ]] || [[ ! "$1" =~ ^[a-z_][a-z0-9_-]*\$?$ ]]; then
  echo "Pemakaian: $0 USER_PHP_FPM (contoh: $0 www)" >&2
  exit 2
fi
php_user="$1"
id "$php_user" >/dev/null 2>&1 || { echo "User PHP-FPM tidak ditemukan: $php_user" >&2; exit 2; }

source_file="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/authorize_db_tunnel_key_root.sh"
worker_source="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/process_db_tunnel_key_requests_root.sh"
helper=/var/lib/finance-secrets/finance-authorize-db-tunnel-key
worker=/usr/local/sbin/finance-process-db-tunnel-key-requests
sudoers=/etc/sudoers.d/finance-db-tunnel-key

if [[ ! -d /var/lib/finance-secrets ]]; then
  install -d -o root -g "$php_user" -m 0750 /var/lib/finance-secrets
fi
install -o root -g root -m 0750 "$source_file" "$helper"
install -d -o "$php_user" -g "$php_user" -m 0700 /var/lib/finance-secrets/db-tunnel-key-requests
install -d -o root -g "$php_user" -m 0750 /var/lib/finance-secrets/db-tunnel-key-status
install -o root -g root -m 0750 "$worker_source" "$worker"
printf '%s ALL=(root) NOPASSWD: %s\n' "$php_user" "$helper" > "$sudoers"
chown root:root "$sudoers"
chmod 0440 "$sudoers"
visudo -cf "$sudoers"
cron_file=/etc/cron.d/finance-db-tunnel-key-worker
printf '* * * * * root %s\n' "$worker" > "$cron_file"
chown root:root "$cron_file"
chmod 0644 "$cron_file"
echo "Worker antrean key tunnel terpasang untuk user PHP-FPM '$php_user'; cron memproses permintaan setiap menit."
