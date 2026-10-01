#!/usr/bin/env bash
# ============================================================
# setup_slave.sh — Konfigurasi MySQL sebagai Slave (Server 2)
# Jalankan SEKALI di Server 2 saat pertama setup replication
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../backup/.env"
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-db_finance}"

# Konfigurasi koneksi ke Master
MASTER_HOST="${MASTER_HOST:-IP_SERVER_1}"
MASTER_PORT="${MASTER_PORT:-3306}"
REPL_USER="${REPL_USER:-repl_user}"
REPL_PASS="${REPL_PASS:-repl_password_ganti_ini}"
MASTER_LOG_FILE="${MASTER_LOG_FILE:-}"     # Dari SHOW MASTER STATUS di Server 1
MASTER_LOG_POS="${MASTER_LOG_POS:-}"       # Dari SHOW MASTER STATUS di Server 1

MYSQL_CMD="mysql --host=${DB_HOST} --user=${DB_USER}"
[ -n "$DB_PASS" ] && MYSQL_CMD="${MYSQL_CMD} --password=${DB_PASS}"

echo "====== Setup MySQL Slave (Server 2) ======"
echo ""

# ── 1) Panduan konfigurasi my.cnf ─────────────────────────
echo "[STEP 1] Pastikan konfigurasi berikut ada di my.cnf / my.ini:"
echo ""
echo "  [mysqld]"
echo "  server-id                = 2           # BERBEDA dari Master"
echo "  log_bin                  = mysql-bin"
echo "  binlog_format            = ROW"
echo "  gtid_mode                = ON"
echo "  enforce_gtid_consistency = ON"
echo "  read_only                = ON           # Slave read-only secara default"
echo "  auto_increment_increment = 2            # Step ID sama: 2"
echo "  auto_increment_offset    = 2            # Server 2 mulai dari ID genap"
echo "  relay_log                = relay-bin"
echo "  log_slave_updates        = ON"
echo ""
echo "  Restart MySQL setelah ubah: sudo systemctl restart mysql"
echo ""
read -p "Sudah restart MySQL? (y/n): " CONFIRM
[ "$CONFIRM" != "y" ] && echo "Setup dibatalkan." && exit 1

# ── 2) Import snapshot dari Master ────────────────────────
echo ""
echo "[STEP 2] Import snapshot database dari Master."
echo "  Di Server 1, jalankan:"
echo "    mysqldump --user=root --single-transaction --master-data=2 \\"
echo "              --gtid-mode=OFF --set-gtid-purged=OFF \\"
echo "              ${DB_NAME} > snapshot_master.sql"
echo "  Lalu transfer ke Server 2 dan import:"
echo "    mysql --user=root ${DB_NAME} < snapshot_master.sql"
echo ""
read -p "Sudah import snapshot? (y/n): " CONFIRM
[ "$CONFIRM" != "y" ] && echo "Setup dibatalkan." && exit 1

# ── 3) Konfigurasi koneksi ke Master ──────────────────────
echo ""
echo "[STEP 3] Konfigurasi slave..."

if [ -z "$MASTER_LOG_FILE" ] || [ -z "$MASTER_LOG_POS" ]; then
  echo "  Masukkan nilai dari SHOW MASTER STATUS di Server 1:"
  read -p "  MASTER_LOG_FILE (misal: mysql-bin.000003): " MASTER_LOG_FILE
  read -p "  MASTER_LOG_POS  (misal: 154): " MASTER_LOG_POS
fi

$MYSQL_CMD -e "
  STOP SLAVE;
  CHANGE MASTER TO
    MASTER_HOST='${MASTER_HOST}',
    MASTER_PORT=${MASTER_PORT},
    MASTER_USER='${REPL_USER}',
    MASTER_PASSWORD='${REPL_PASS}',
    MASTER_LOG_FILE='${MASTER_LOG_FILE}',
    MASTER_LOG_POS=${MASTER_LOG_POS};
  START SLAVE;
"

echo ""
echo "[STEP 4] Status Slave:"
$MYSQL_CMD -e "SHOW SLAVE STATUS\G" | grep -E "Slave_IO_Running|Slave_SQL_Running|Seconds_Behind_Master|Last_Error"

echo ""
echo "====== Setup Slave Selesai ======"
echo "Pantau: Slave_IO_Running dan Slave_SQL_Running harus YES"
echo "Cek berkala: scripts/replication/health_check.sh"
