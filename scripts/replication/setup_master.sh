#!/usr/bin/env bash
# ============================================================
# setup_master.sh — Konfigurasi MySQL sebagai Master (Server 1)
# Jalankan SEKALI saat pertama setup replication
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../backup/.env"
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-db_finance}"

REPL_USER="${REPL_USER:-repl_user}"
REPL_PASS="${REPL_PASS:-repl_password_ganti_ini}"

MYSQL_CMD="mysql --host=${DB_HOST} --user=${DB_USER}"
[ -n "$DB_PASS" ] && MYSQL_CMD="${MYSQL_CMD} --password=${DB_PASS}"

echo "====== Setup MySQL Master (Server 1) ======"
echo "Host    : ${DB_HOST}"
echo "Database: ${DB_NAME}"
echo ""

# ── 1) Cek my.cnf atau my.ini ──────────────────────────────
echo "[STEP 1] Pastikan konfigurasi berikut ada di my.cnf / my.ini:"
echo ""
echo "  [mysqld]"
echo "  server-id          = 1           # UNIK per server"
echo "  log_bin            = mysql-bin   # Aktifkan binary log"
echo "  binlog_format      = ROW         # ROW lebih aman untuk replication"
echo "  expire_logs_days   = 7           # Hapus binlog > 7 hari"
echo "  gtid_mode          = ON          # Aktifkan GTID (MySQL 5.7+)"
echo "  enforce_gtid_consistency = ON"
echo "  auto_increment_increment = 2     # Step ID: 1, 3, 5, 7..."
echo "  auto_increment_offset    = 1     # Server 1 mulai dari ID ganjil"
echo ""
echo "  Restart MySQL setelah ubah: sudo systemctl restart mysql"
echo ""
read -p "Sudah restart MySQL? (y/n): " CONFIRM
[ "$CONFIRM" != "y" ] && echo "Setup dibatalkan." && exit 1

# ── 2) Buat user replication ───────────────────────────────
echo ""
echo "[STEP 2] Membuat user replication..."
$MYSQL_CMD -e "
  CREATE USER IF NOT EXISTS '${REPL_USER}'@'%' IDENTIFIED BY '${REPL_PASS}';
  GRANT REPLICATION SLAVE ON *.* TO '${REPL_USER}'@'%';
  FLUSH PRIVILEGES;
"
echo "  User replication OK: ${REPL_USER}"

# ── 3) Tampilkan status master ──────────────────────────────
echo ""
echo "[STEP 3] Status Master:"
$MYSQL_CMD -e "SHOW MASTER STATUS\G"

echo ""
echo "====== CATAT nilai File dan Position di atas! ======"
echo "Dibutuhkan saat setup Slave (setup_slave.sh)"
echo ""
echo "Selanjutnya: jalankan setup_slave.sh di Server 2"
