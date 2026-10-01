#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FINANCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "[ERROR] File .env tidak ditemukan di ${SCRIPT_DIR}"
  echo "        Salin .env.example menjadi .env lalu isi token dan zone Cloudflare."
  exit 1
fi

# shellcheck source=/dev/null
source "$ENV_FILE"

CF_API_TOKEN="${CF_API_TOKEN:-}"
CF_ZONE_ID="${CF_ZONE_ID:-}"
CF_RECORD_TYPES="${CF_RECORD_TYPES:-A}"
CF_INCLUDE_NAMES="${CF_INCLUDE_NAMES:-}"
CF_EXCLUDE_NAMES="${CF_EXCLUDE_NAMES:-}"
CF_DELETE_IDENTICAL_CONFLICTS="${CF_DELETE_IDENTICAL_CONFLICTS:-0}"
CF_RECORD_IDS_FILE="${CF_RECORD_IDS_FILE:-}"
CACHE_FILE="${CF_CACHE_FILE:-${FINANCE_ROOT}/backup/cloudflare/public_ipv4.txt}"
LOCK_DIR="${CF_LOCK_DIR:-/tmp/finance-cloudflare-ddns.lock}"
CF_API_BASE="${CF_API_BASE:-https://api.cloudflare.com/client/v4}"

if [ -z "$CF_API_TOKEN" ] || [ -z "$CF_ZONE_ID" ]; then
  echo "[ERROR] CF_API_TOKEN dan CF_ZONE_ID wajib diisi."
  exit 1
fi

mkdir -p "$(dirname "$CACHE_FILE")"

if ! mkdir "$LOCK_DIR" 2>/dev/null; then
  echo "[INFO] Proses DDNS lain masih berjalan. Lewati eksekusi ini."
  exit 0
fi
trap 'rmdir "$LOCK_DIR" 2>/dev/null || true' EXIT

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

trim() {
  local value="${1:-}"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf '%s' "$value"
}

csv_contains() {
  local csv="${1:-}"
  local needle="${2:-}"
  if [ -z "$csv" ] || [ -z "$needle" ]; then
    return 1
  fi

  local part
  IFS=',' read -ra parts <<< "$csv"
  for part in "${parts[@]}"; do
    part="$(trim "$part")"
    if [ "$part" = "$needle" ]; then
      return 0
    fi
  done

  return 1
}

is_valid_ipv4() {
  local ip="${1:-}"
  if [[ ! "$ip" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
    return 1
  fi

  local octet
  IFS='.' read -ra octets <<< "$ip"
  for octet in "${octets[@]}"; do
    if [ "$octet" -lt 0 ] || [ "$octet" -gt 255 ]; then
      return 1
    fi
  done

  return 0
}

get_public_ipv4() {
  local services=(
    "https://api.ipify.org"
    "https://ipv4.icanhazip.com"
    "https://ifconfig.me/ip"
  )
  local service
  local value

  for service in "${services[@]}"; do
    value="$(curl -4fsS --max-time 10 "$service" 2>/dev/null | tr -d '\r' | head -n 1 | xargs || true)"
    if is_valid_ipv4 "$value"; then
      printf '%s' "$value"
      return 0
    fi
  done

  return 1
}

api_get() {
  local url="$1"
  curl -fsS -X GET "$url" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H "Content-Type: application/json"
}

api_patch() {
  local url="$1"
  local payload="$2"
  curl -sS -X PATCH "$url" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H "Content-Type: application/json" \
    --data "$payload"
}

api_delete() {
  local url="$1"
  curl -sS -X DELETE "$url" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H "Content-Type: application/json"
}

# Some legacy records may be addressable by ID but absent from a filtered list
# response.  Load those IDs as a safe fallback without allowing an ID to point
# to a different hostname than the one declared in the file.
load_record_id_fallbacks() {
  [ -n "$CF_RECORD_IDS_FILE" ] || return 0

  if [ ! -r "$CF_RECORD_IDS_FILE" ]; then
    log "[WARN] File fallback record ID tidak dapat dibaca: ${CF_RECORD_IDS_FILE}"
    return 0
  fi

  local fallback_id fallback_name extra loaded row response ok actual_id actual_name error_message
  while read -r fallback_id fallback_name extra; do
    [ -n "${fallback_id:-}" ] || continue
    [[ "$fallback_id" = \#* ]] && continue

    if [[ ! "$fallback_id" =~ ^[[:xdigit:]]{32}$ ]] || [ -z "${fallback_name:-}" ] || [ -n "${extra:-}" ]; then
      log "[WARN] Lewati format fallback record ID yang tidak valid."
      continue
    fi

    loaded=0
    for row in "${records[@]-}"; do
      [ "$(printf '%s' "$row" | jq -r '.id')" = "$fallback_id" ] && { loaded=1; break; }
    done
    [ "$loaded" -eq 0 ] || continue

    response="$(api_get "${CF_API_BASE}/zones/${CF_ZONE_ID}/dns_records/${fallback_id}" || true)"
    ok="$(printf '%s' "$response" | jq -r '.success // false' 2>/dev/null || printf 'false')"
    if [ "$ok" != "true" ]; then
      error_message="$(printf '%s' "$response" | jq -r '.errors[0].message // "respons API tidak valid"' 2>/dev/null || printf 'respons API tidak valid')"
      log "[WARN] Fallback ${fallback_name} tidak tersedia: ${error_message}"
      continue
    fi

    actual_id="$(printf '%s' "$response" | jq -r '.result.id // empty')"
    actual_name="$(printf '%s' "$response" | jq -r '.result.name // empty')"
    if [ "$actual_id" != "$fallback_id" ] || [ "$actual_name" != "$fallback_name" ]; then
      log "[WARN] Fallback record ID tidak cocok dengan host ${fallback_name}; dilewati."
      continue
    fi

    records+=("$(printf '%s' "$response" | jq -c '.result')")
  done < "$CF_RECORD_IDS_FILE"
}

CURRENT_IP="$(get_public_ipv4 || true)"
if ! is_valid_ipv4 "$CURRENT_IP"; then
  log "[ERROR] Tidak bisa mendapatkan IPv4 publik yang valid."
  exit 1
fi

LAST_IP=""
if [ -f "$CACHE_FILE" ]; then
  LAST_IP="$(tr -d '\r\n' < "$CACHE_FILE")"
fi

FORCE_RUN=0
if [ "${1:-}" = "--force" ]; then
  FORCE_RUN=1
fi

if [ "$FORCE_RUN" -eq 0 ] && [ -n "$LAST_IP" ] && [ "$CURRENT_IP" = "$LAST_IP" ]; then
  log "[INFO] IPv4 publik belum berubah (${CURRENT_IP})."
  exit 0
fi

log "[INFO] IPv4 publik: ${LAST_IP:-<kosong>} -> ${CURRENT_IP}"

page=1
total_pages=1
declare -a records

while [ "$page" -le "$total_pages" ]; do
  response="$(api_get "${CF_API_BASE}/zones/${CF_ZONE_ID}/dns_records?per_page=100&page=${page}&type=${CF_RECORD_TYPES}")"
  success="$(printf '%s' "$response" | jq -r '.success // false')"
  if [ "$success" != "true" ]; then
    log "[ERROR] Gagal mengambil daftar record Cloudflare."
    printf '%s\n' "$response"
    exit 1
  fi

  while IFS= read -r row; do
    [ -n "$row" ] && records+=("$row")
  done < <(printf '%s' "$response" | jq -c '.result[]')

  total_pages="$(printf '%s' "$response" | jq -r '.result_info.total_pages // 1')"
  page=$((page + 1))
done

load_record_id_fallbacks

if [ "${#records[@]}" -eq 0 ]; then
  log "[WARN] Tidak ada record ${CF_RECORD_TYPES} yang ditemukan."
  exit 0
fi

updated=0
skipped=0
failed=0

for record in "${records[@]}"; do
  record_id="$(printf '%s' "$record" | jq -r '.id')"
  record_name="$(printf '%s' "$record" | jq -r '.name')"
  record_type="$(printf '%s' "$record" | jq -r '.type')"
  record_content="$(printf '%s' "$record" | jq -r '.content')"
  record_ttl="$(printf '%s' "$record" | jq -r '.ttl // 1')"
  record_proxied="$(printf '%s' "$record" | jq -r '.proxied // false')"

  if [ -n "$CF_INCLUDE_NAMES" ] && ! csv_contains "$CF_INCLUDE_NAMES" "$record_name"; then
    skipped=$((skipped + 1))
    continue
  fi

  if csv_contains "$CF_EXCLUDE_NAMES" "$record_name"; then
    skipped=$((skipped + 1))
    continue
  fi

  if [ "$record_type" != "A" ]; then
    skipped=$((skipped + 1))
    continue
  fi

  if [ "$record_content" = "$CURRENT_IP" ]; then
    skipped=$((skipped + 1))
    continue
  fi

  payload="$(jq -nc \
    --arg type "$record_type" \
    --arg name "$record_name" \
    --arg content "$CURRENT_IP" \
    --argjson ttl "$record_ttl" \
    --argjson proxied "$record_proxied" \
    '{type:$type,name:$name,content:$content,ttl:$ttl,proxied:$proxied}')"

  result="$(api_patch "${CF_API_BASE}/zones/${CF_ZONE_ID}/dns_records/${record_id}" "$payload" || true)"
  ok="$(printf '%s' "$result" | jq -r '.success // false' 2>/dev/null || printf 'false')"

  if [ "$ok" = "true" ]; then
    updated=$((updated + 1))
    log "[OK] ${record_name} -> ${CURRENT_IP}"
  else
    err_code="$(printf '%s' "$result" | jq -r '.errors[0].code // 0' 2>/dev/null || printf '0')"
    if [ "$err_code" = "81058" ] && [ "$CF_DELETE_IDENTICAL_CONFLICTS" = "1" ]; then
      delete_result="$(api_delete "${CF_API_BASE}/zones/${CF_ZONE_ID}/dns_records/${record_id}" || true)"
      delete_ok="$(printf '%s' "$delete_result" | jq -r '.success // false' 2>/dev/null || printf 'false')"
      if [ "$delete_ok" = "true" ]; then
        updated=$((updated + 1))
        log "[OK] ${record_name} duplikat identik dihapus (record_id=${record_id})"
        continue
      fi
      result="$delete_result"
    fi

    failed=$((failed + 1))
    log "[FAIL] ${record_name} (record_id=${record_id})"
    printf '%s\n' "$result"
  fi
done

if [ "$failed" -eq 0 ]; then
  printf '%s\n' "$CURRENT_IP" > "$CACHE_FILE"
fi

log "[DONE] updated=${updated} skipped=${skipped} failed=${failed}"

if [ "$failed" -gt 0 ]; then
  exit 1
fi

exit 0
