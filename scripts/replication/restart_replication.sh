#!/usr/bin/env bash
# ============================================================
# restart_replication.sh — Restart replication S1→S2 setelah recovery
# Jalankan di Server 2 setelah sync data selesai
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../backup/.env"
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

FINANCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
MASTER_HOST="${MASTER_HOST:-IP_SERVER_1}"
MASTER_PORT="${MASTER_PORT:-3306}"
REPL_USER="${REPL_USER:-repl_user}"
REPL_PASS="${REPL_PASS:-repl_password_ganti_ini}"

MYSQL_CMD="mysql --host=${DB_HOST} --user=${DB_USER}"
[ -n "$DB_PASS" ] && MYSQL_CMD="${MYSQL_CMD} --password=${DB_PASS}"

echo "====== Restart Replication: Server 2 → Slave kembali ======"
echo ""

# Ambil posisi binlog terkini dari Server 1
MASTER_STATUS=$(mysql --host="${MASTER_HOST}" --user="${DB_USER}" ${DB_PASS:+--password="${DB_PASS}"} -e "SHOW MASTER STATUS\G" 2>/dev/null)
BINLOG_FILE=$(echo "$MASTER_STATUS" | grep "File:" | awk '{print $2}')
BINLOG_POS=$(echo "$MASTER_STATUS" | grep "Position:" | awk '{print $2}')

echo "Master binlog: ${BINLOG_FILE} @ ${BINLOG_POS}"
echo ""
read -p "Lanjutkan restart replication? (y/n): " CONFIRM
[ "$CONFIRM" != "y" ] && echo "Dibatalkan." && exit 0

$MYSQL_CMD -e "
  STOP SLAVE;
  SET GLOBAL read_only = ON;
  CHANGE MASTER TO
    MASTER_HOST='${MASTER_HOST}',
    MASTER_PORT=${MASTER_PORT},
    MASTER_USER='${REPL_USER}',
    MASTER_PASSWORD='${REPL_PASS}',
    MASTER_LOG_FILE='${BINLOG_FILE}',
    MASTER_LOG_POS=${BINLOG_POS};
  START SLAVE;
"

sleep 2
echo ""
echo "Status Slave setelah restart:"
$MYSQL_CMD -e "SHOW SLAVE STATUS\G" | grep -E "Slave_IO_Running|Slave_SQL_Running|Seconds_Behind_Master|Last_Error"

# Hapus failover time log
rm -f "${FINANCE_ROOT}/backup/logs/failover_time.txt"

echo ""
echo "====== Replication restart SELESAI ======"
echo "Server 2 kembali sebagai Slave dari Server 1."
