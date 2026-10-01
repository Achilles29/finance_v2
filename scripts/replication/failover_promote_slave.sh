#!/usr/bin/env bash
# ============================================================
# failover_promote_slave.sh — Promosikan Slave menjadi aktif
# Jalankan di Server 2 ketika Server 1 (Master) DOWN
# MANUAL — tidak otomatis!
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../backup/.env"
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
FINANCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

MYSQL_CMD="mysql --host=${DB_HOST} --user=${DB_USER}"
[ -n "$DB_PASS" ] && MYSQL_CMD="${MYSQL_CMD} --password=${DB_PASS}"

echo "====== FAILOVER: Promosi Slave → Master Sementara ======"
echo ""
echo "PERINGATAN: Jalankan ini HANYA jika Server 1 (Master) benar-benar DOWN"
echo "dan tidak bisa diakses. Proses ini bersifat MANUAL dan PERMANEN sampai"
echo "Server 1 kembali online dan recovery dilakukan."
echo ""
read -p "Konfirmasi: Server 1 DOWN dan kamu ingin aktifkan Server 2? (y/n): " CONFIRM
[ "$CONFIRM" != "y" ] && echo "Dibatalkan." && exit 0

# Catat waktu failover dimulai (dibutuhkan untuk recovery)
FAILOVER_TIME=$(date '+%Y-%m-%d %H:%M:%S')
FAILOVER_FILE="${FINANCE_ROOT}/backup/logs/failover_time.txt"

echo "$FAILOVER_TIME" > "$FAILOVER_FILE"
echo "Failover time: ${FAILOVER_TIME}" >> "$FAILOVER_FILE"

# ── Stop slave, jadikan standalone ──────────────────────────
echo ""
echo "[1] Menghentikan slave replication..."
$MYSQL_CMD -e "STOP SLAVE; RESET SLAVE ALL;"
echo "    Slave dihentikan."

# ── Aktifkan write mode ────────────────────────────────────
echo "[2] Mengaktifkan write mode (hapus read_only)..."
$MYSQL_CMD -e "SET GLOBAL read_only = OFF; SET GLOBAL super_read_only = OFF;"
echo "    Server 2 sekarang bisa menerima write."

# ── Update settings di app ────────────────────────────────
echo ""
echo "[3] Update konfigurasi aplikasi:"
echo "    Buka halaman Settings → Replication di Finance app"
echo "    Ubah SERVER_ROLE = STANDALONE (atau TEMPORARY_MASTER)"
echo ""

echo "====== Failover SELESAI ======"
echo "Server 2 sekarang aktif sebagai standalone master sementara."
echo "Catat waktu failover: ${FAILOVER_TIME}"
echo ""
echo "Ketika Server 1 kembali online, jalankan:"
echo "  scripts/replication/recovery_sync.sh"
