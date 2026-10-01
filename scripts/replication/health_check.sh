#!/usr/bin/env bash
# ============================================================
# health_check.sh — Cek status replication setiap beberapa menit
# Cron: */5 * * * * /path/to/finance/scripts/replication/health_check.sh
# ============================================================

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FINANCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${SCRIPT_DIR}/../backup/.env"
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
SERVER_ROLE="${SERVER_ROLE:-SLAVE}"   # MASTER atau SLAVE
MASTER_HOST="${MASTER_HOST:-}"
STATUS_FILE="${FINANCE_ROOT}/backup/logs/replication_status.json"

MYSQL_CMD="mysql --host=${DB_HOST} --user=${DB_USER} --batch --silent"
[ -n "$DB_PASS" ] && MYSQL_CMD="${MYSQL_CMD} --password=${DB_PASS}"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
NOW=$(date '+%Y-%m-%d %H:%M:%S')

# ── Cek koneksi DB lokal ───────────────────────────────────
if ! $MYSQL_CMD -e "SELECT 1" &>/dev/null; then
  log "[ALERT] DB lokal tidak bisa diakses!"
  echo "{\"timestamp\":\"${NOW}\",\"status\":\"DB_DOWN\",\"role\":\"${SERVER_ROLE}\"}" > "$STATUS_FILE"
  exit 1
fi

STATUS_DATA="{\"timestamp\":\"${NOW}\",\"role\":\"${SERVER_ROLE}\","

if [ "$SERVER_ROLE" = "SLAVE" ]; then
  # ── Status slave ──────────────────────────────────────────
  IO_RUNNING=$($MYSQL_CMD -e "SHOW SLAVE STATUS\G" | grep "Slave_IO_Running" | awk '{print $2}')
  SQL_RUNNING=$($MYSQL_CMD -e "SHOW SLAVE STATUS\G" | grep "Slave_SQL_Running:" | awk '{print $2}')
  LAG=$($MYSQL_CMD -e "SHOW SLAVE STATUS\G" | grep "Seconds_Behind_Master" | awk '{print $2}')
  LAST_ERROR=$($MYSQL_CMD -e "SHOW SLAVE STATUS\G" | grep "Last_SQL_Error:" | cut -d: -f2-)

  if [ "$IO_RUNNING" = "Yes" ] && [ "$SQL_RUNNING" = "Yes" ]; then
    log "Slave OK — Lag: ${LAG}s"
    STATUS_DATA+="\"status\":\"OK\",\"io_running\":\"${IO_RUNNING}\",\"sql_running\":\"${SQL_RUNNING}\",\"lag_seconds\":${LAG:-0}}"
  else
    log "[ALERT] Slave ERROR — IO: ${IO_RUNNING}, SQL: ${SQL_RUNNING}, Error: ${LAST_ERROR}"
    STATUS_DATA+="\"status\":\"ERROR\",\"io_running\":\"${IO_RUNNING}\",\"sql_running\":\"${SQL_RUNNING}\",\"lag_seconds\":${LAG:-0},\"error\":\"$(echo $LAST_ERROR | tr '"' "'")\"}"
  fi
else
  # ── Status master ──────────────────────────────────────────
  BINLOG=$($MYSQL_CMD -e "SHOW MASTER STATUS\G" | grep "File:" | awk '{print $2}')
  POSITION=$($MYSQL_CMD -e "SHOW MASTER STATUS\G" | grep "Position:" | awk '{print $2}')
  log "Master OK — Binlog: ${BINLOG}, Pos: ${POSITION}"
  STATUS_DATA+="\"status\":\"OK\",\"binlog\":\"${BINLOG}\",\"position\":${POSITION:-0}}"
fi

echo "$STATUS_DATA" > "$STATUS_FILE"
