#!/usr/bin/env bash
set -euo pipefail

readonly base=/var/lib/finance-secrets
readonly inbox="$base/db-tunnel-key-requests"
readonly processing="$base/db-tunnel-key-processing"
readonly statuses="$base/db-tunnel-key-status"
readonly authorizer="$base/finance-authorize-db-tunnel-key"
readonly status_group="$(stat -c %G -- "$statuses" 2>/dev/null || echo www)"

[[ -d "$inbox" && -d "$statuses" && -x "$authorizer" ]] || exit 1
install -d -o root -g root -m 0700 "$processing"
shopt -s nullglob

for request in "$inbox"/*.req; do
  request_id="${request##*/}"
  request_id="${request_id%.req}"
  [[ "$request_id" =~ ^[a-f0-9]{32}$ ]] || continue
  staged="$processing/$request_id.req"
  if ! mv -- "$request" "$staged" 2>/dev/null; then continue; fi

  result=reject
  if [[ -f "$staged" && ! -L "$staged" && $(stat -c %s -- "$staged" 2>/dev/null || echo 9999) -le 1024 ]]; then
    if "$authorizer" < "$staged" >/dev/null 2>&1; then result=ok; fi
  fi
  mv -- "$staged" "$processing/$request_id.done"

  status_tmp="$statuses/.$request_id.$$"
  printf '%s\n' "$result" > "$status_tmp"
  chown "root:$status_group" "$status_tmp"
  chmod 0640 "$status_tmp"
  mv -- "$status_tmp" "$statuses/$request_id.status"
done
