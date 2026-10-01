#!/usr/bin/env bash
# ============================================================
# backup_full.sh — Full local DB dump
# Jadwalkan via cron setiap 30 menit
#
# Setup cron (jalankan: crontab -e):
#   */30 * * * * /path/to/finance/scripts/backup/backup_full.sh >> /path/to/finance/backup/logs/cron.log 2>&1
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FINANCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# ── Load .env ──────────────────────────────────────────────
ENV_FILE="${SCRIPT_DIR}/.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "[ERROR] File .env tidak ditemukan di ${SCRIPT_DIR}"
  echo "        Salin .env.example ke .env dan isi konfigurasi."
  exit 1
fi
# shellcheck source=/dev/null
source "$ENV_FILE"

# ── Defaults ───────────────────────────────────────────────
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-db_finance}"
BACKUP_DIR_VALUE="${BACKUP_DIR:-/var/lib/finance-backup/dumps}"
LOG_DIR_VALUE="${LOG_DIR:-/var/lib/finance-backup/logs}"
if [[ "$BACKUP_DIR_VALUE" = /* ]]; then BACKUP_DIR="$BACKUP_DIR_VALUE"; else BACKUP_DIR="${FINANCE_ROOT}/${BACKUP_DIR_VALUE}"; fi
if [[ "$LOG_DIR_VALUE" = /* ]]; then LOG_DIR="$LOG_DIR_VALUE"; else LOG_DIR="${FINANCE_ROOT}/${LOG_DIR_VALUE}"; fi
EXCLUDE_TABLES="${EXCLUDE_TABLES:-}"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOGFILE="${LOG_DIR}/backup_${TIMESTAMP}.log"
DUMPFILE="${BACKUP_DIR}/backup_${DB_NAME}_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR" "$LOG_DIR"
chmod 700 "$BACKUP_DIR" "$LOG_DIR"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOGFILE"; }

log "====== Backup START: ${TIMESTAMP} ======"
log "Database : ${DB_NAME}@${DB_HOST}:${DB_PORT}"
log "Output   : ${DUMPFILE}"
log "Storage  : LOCAL-ONLY on this host"
log "Off-site : SEPARATE encrypted backup must be configured independently"

# ── Build ignore tables args ───────────────────────────────
IGNORE_ARGS=""
if [ -n "$EXCLUDE_TABLES" ]; then
  IFS=',' read -ra TABLES <<< "$EXCLUDE_TABLES"
  for tbl in "${TABLES[@]}"; do
    tbl=$(echo "$tbl" | tr -d ' ')
    IGNORE_ARGS="$IGNORE_ARGS --ignore-table=${DB_NAME}.${tbl}"
  done
  log "Exclude  : ${EXCLUDE_TABLES}"
fi

# ── Dump ───────────────────────────────────────────────────
# Gunakan MYSQL_PWD env var agar password tidak muncul di process list
# dan tidak ada masalah quoting di command line.
export MYSQL_PWD="${DB_PASS}"

MYSQL_OPTS=(
  "--host=${DB_HOST}"
  "--port=${DB_PORT}"
  "--user=${DB_USER}"
  "--single-transaction"
  "--routines"
  "--events"
  "--no-tablespaces"
  "--skip-lock-tables"
)
# --set-gtid-purged=OFF hanya tersedia di MySQL 5.6.2+; cek dulu sebelum pakai
if mysqldump --help 2>/dev/null | grep -q 'set-gtid-purged'; then
  MYSQL_OPTS+=("--set-gtid-purged=OFF")
fi
[ -n "$IGNORE_ARGS" ] && MYSQL_OPTS+=($IGNORE_ARGS)

if mysqldump "${MYSQL_OPTS[@]}" "$DB_NAME" | gzip -9 > "$DUMPFILE"; then
  DUMP_HASH=$(sha256sum "$DUMPFILE" | awk '{print $1}')
  printf '%s  %s\n' "$DUMP_HASH" "$(basename "$DUMPFILE")" > "${DUMPFILE}.sha256"
  chmod 600 "$DUMPFILE" "${DUMPFILE}.sha256"
  SIZE=$(du -sh "$DUMPFILE" | cut -f1)
  log "Dump OK  : ${SIZE}"
  log "SHA-256 : ${DUMP_HASH}"
else
  log "[ERROR] mysqldump gagal!"
  exit 1
fi

# Retention tidak menghapus file langsung. Jalankan retention_manager.php plan;
# apply hanya memindahkan kandidat tervalidasi ke quarantine berjejak.
log "Retention: tidak ada penghapusan otomatis; gunakan A5.15 retention manager"

log "====== Backup SELESAI ======"
log ""
