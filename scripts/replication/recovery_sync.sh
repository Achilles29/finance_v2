#!/usr/bin/env bash
# ============================================================
# recovery_sync.sh — Sinkronisasi data setelah Server 1 kembali
# Jalankan di Server 2 setelah Server 1 ONLINE kembali
# Skema: Server 2 (punya data baru) → Server 1 (ketinggalan)
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FINANCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${SCRIPT_DIR}/../backup/.env"
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

# ── Konfigurasi ────────────────────────────────────────────
DB_HOST_S2="${DB_HOST:-localhost}"      # Server 2 (ini, yang aktif)
DB_HOST_S1="${MASTER_HOST:-IP_SERVER_1}"  # Server 1 (yang baru online)
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-db_finance}"

MYSQL_S2="mysql --host=${DB_HOST_S2} --user=${DB_USER} --batch"
MYSQL_S1="mysql --host=${DB_HOST_S1} --user=${DB_USER} --batch"
[ -n "$DB_PASS" ] && MYSQL_S2="${MYSQL_S2} --password=${DB_PASS}" && MYSQL_S1="${MYSQL_S1} --password=${DB_PASS}"

FAILOVER_FILE="${FINANCE_ROOT}/backup/logs/failover_time.txt"
SYNC_DIR="${FINANCE_ROOT}/backup/sync_$(date +%Y%m%d_%H%M%S)"

mkdir -p "$SYNC_DIR"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

echo "====== Recovery Sync: Server 2 → Server 1 ======"
echo ""

# ── 1) Baca waktu failover ─────────────────────────────────
if [ ! -f "$FAILOVER_FILE" ]; then
  echo "[ERROR] File failover_time.txt tidak ditemukan."
  echo "        Masukkan waktu failover manual (format: YYYY-MM-DD HH:MM:SS):"
  read -p "  Failover time: " FAILOVER_TIME
else
  FAILOVER_TIME=$(head -1 "$FAILOVER_FILE")
  log "Failover time dari log: ${FAILOVER_TIME}"
fi

# ── 2) Cek koneksi ke Server 1 ─────────────────────────────
log "Cek koneksi ke Server 1 (${DB_HOST_S1})..."
if ! $MYSQL_S1 -e "SELECT 1" &>/dev/null; then
  echo "[ERROR] Server 1 tidak bisa diakses dari sini!"
  exit 1
fi
log "Server 1 OK"

# ── 3) Ambil daftar tabel ──────────────────────────────────
log "Mengambil daftar tabel dari ${DB_NAME}..."
TABLES=$($MYSQL_S2 -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_TYPE='BASE TABLE'" --skip-column-names)

DIFF_REPORT="${SYNC_DIR}/diff_report.txt"
SYNC_SQL="${SYNC_DIR}/sync_to_server1.sql"

echo "====== DIFF REPORT: Server 2 vs Server 1 ======" > "$DIFF_REPORT"
echo "Failover time : ${FAILOVER_TIME}" >> "$DIFF_REPORT"
echo "Generated at  : $(date)" >> "$DIFF_REPORT"
echo "" >> "$DIFF_REPORT"

echo "-- Sync SQL: data baru di Server 2 → Server 1" > "$SYNC_SQL"
echo "-- Generated: $(date)" >> "$SYNC_SQL"
echo "-- PERIKSA FILE INI SEBELUM DIJALANKAN!" >> "$SYNC_SQL"
echo "" >> "$SYNC_SQL"

TOTAL_DIFF=0

# ── 4) Bandingkan tiap tabel ───────────────────────────────
log "Membandingkan tabel..."
for TABLE in $TABLES; do
  # Skip tabel sistem/log yang tidak perlu disync
  if [[ "$TABLE" =~ ^(sys_audit_log|att_presence)$ ]]; then
    continue
  fi

  # Cek apakah tabel punya kolom updated_at
  HAS_UPDATED=$($MYSQL_S2 -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${TABLE}' AND COLUMN_NAME='updated_at'" --skip-column-names)

  if [ "$HAS_UPDATED" -eq 1 ]; then
    # Hitung baris yang berubah/baru di Server 2 sejak failover
    COUNT_NEW=$($MYSQL_S2 -e "SELECT COUNT(*) FROM ${DB_NAME}.${TABLE} WHERE updated_at >= '${FAILOVER_TIME}'" --skip-column-names 2>/dev/null || echo "0")
    COUNT_S2=$($MYSQL_S2 -e "SELECT COUNT(*) FROM ${DB_NAME}.${TABLE}" --skip-column-names 2>/dev/null || echo "0")
    COUNT_S1=$($MYSQL_S1 -e "SELECT COUNT(*) FROM ${DB_NAME}.${TABLE}" --skip-column-names 2>/dev/null || echo "0")

    if [ "$COUNT_NEW" -gt 0 ] || [ "$COUNT_S2" != "$COUNT_S1" ]; then
      echo "TABLE: ${TABLE}" >> "$DIFF_REPORT"
      echo "  Server 2 total : ${COUNT_S2}" >> "$DIFF_REPORT"
      echo "  Server 1 total : ${COUNT_S1}" >> "$DIFF_REPORT"
      echo "  Baru di S2 (>= failover time): ${COUNT_NEW}" >> "$DIFF_REPORT"
      echo "" >> "$DIFF_REPORT"
      TOTAL_DIFF=$((TOTAL_DIFF + COUNT_NEW))

      # Generate INSERT IGNORE untuk baris baru
      if [ "$COUNT_NEW" -gt 0 ]; then
        echo "-- Tabel: ${TABLE} (${COUNT_NEW} baris baru)" >> "$SYNC_SQL"
        $MYSQL_S2 --skip-column-names -e "
          SELECT CONCAT('INSERT IGNORE INTO ${TABLE} VALUES (',
            GROUP_CONCAT(QUOTE(col) ORDER BY 1 SEPARATOR ', '), ');')
          FROM (SELECT * FROM ${DB_NAME}.${TABLE} WHERE updated_at >= '${FAILOVER_TIME}') t
        " >> "$SYNC_SQL" 2>/dev/null || echo "-- [SKIP] ${TABLE}: gagal generate, export manual" >> "$SYNC_SQL"
        echo "" >> "$SYNC_SQL"
      fi
    fi
  fi
done

echo "" >> "$DIFF_REPORT"
echo "Total baris berbeda: ${TOTAL_DIFF}" >> "$DIFF_REPORT"

log "Diff report: ${DIFF_REPORT}"
log "Sync SQL   : ${SYNC_SQL}"
log "Total baris berbeda: ${TOTAL_DIFF}"

# ── 5) Tampilkan hasil ─────────────────────────────────────
echo ""
echo "====== HASIL DIFF ======"
cat "$DIFF_REPORT"

if [ "$TOTAL_DIFF" -eq 0 ]; then
  echo ""
  echo "✓ Tidak ada perbedaan data. Aman untuk restart replication."
  echo ""
  echo "Restart replication (Server 2 kembali jadi Slave):"
  echo "  mysql -e \"CHANGE MASTER TO MASTER_HOST='${DB_HOST_S1}', ...; START SLAVE;\""
else
  echo ""
  echo "⚠ Ada ${TOTAL_DIFF} baris yang perlu disinkronkan."
  echo ""
  echo "LANGKAH:"
  echo "1. Periksa file: ${SYNC_SQL}"
  echo "2. Jika aman, jalankan di Server 1:"
  echo "   mysql --host=${DB_HOST_S1} ${DB_NAME} < ${SYNC_SQL}"
  echo "3. Verifikasi, lalu restart replication:"
  echo "   scripts/replication/restart_replication.sh"
fi
