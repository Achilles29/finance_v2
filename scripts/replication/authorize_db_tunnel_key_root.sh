#!/usr/bin/env bash
set -euo pipefail

readonly auth_file=/root/.ssh/authorized_keys
readonly expected_prefix='command="/bin/false",restrict,port-forwarding,permitopen="127.0.0.1:3306" ssh-ed25519 '
readonly key_pattern='^command="/bin/false",restrict,port-forwarding,permitopen="127\.0\.0\.1:3306"[[:space:]]ssh-ed25519[[:space:]][A-Za-z0-9+/=]+([[:space:]]finance-db-tunnel)?$'
IFS= read -r key_line || true

if [[ "$key_line" != "$expected_prefix"* ]] \
  || [[ ! "$key_line" =~ $key_pattern ]]; then
  echo "Format key ditolak; hanya key DB tunnel terbatas yang diizinkan." >&2
  exit 2
fi

if [[ -L /root/.ssh ]] || [[ -L "$auth_file" ]]; then
  echo "Path authorized_keys tidak boleh berupa symlink." >&2
  exit 3
fi
install -d -o root -g root -m 0700 /root/.ssh
if [[ -e "$auth_file" && ! -f "$auth_file" ]]; then
  echo "authorized_keys bukan file reguler." >&2
  exit 3
fi
touch "$auth_file"
chown root:root "$auth_file"
chmod 0600 "$auth_file"

exec 9>>"$auth_file"
flock -x 9
if grep -Fqx -- "$key_line" "$auth_file"; then
  echo "Key ini sudah terpasang; tidak ada perubahan."
  exit 0
fi
printf '%s\n' "$key_line" >&9
echo "Key tunnel database berhasil ditambahkan dengan pembatasan tanpa shell."
