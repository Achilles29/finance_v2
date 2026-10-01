#!/usr/bin/env bash
# ============================================================
# tunnel_start.sh — SSH tunnel dari laptop ke Server 1 (Master)
# Dipakai jika Server 2 adalah laptop tanpa IP publik
#
# Cara kerja: port 3307 lokal → port 3306 Server 1 (via SSH)
# MySQL slave connect ke 127.0.0.1:3307 (bukan ke IP server langsung)
#
# Setup auto-start (Linux):
#   1. Install autossh: sudo apt install autossh
#   2. Crontab: @reboot autossh -M 0 -N -f -L 3307:127.0.0.1:3306 user@SERVER1_IP
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../backup/.env"
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

MASTER_HOST="${MASTER_HOST:-IP_ATAU_DOMAIN_SERVER_1}"
SSH_USER="${SSH_USER:-root}"
LOCAL_PORT="${TUNNEL_LOCAL_PORT:-3307}"
REMOTE_PORT="${MASTER_PORT:-3306}"

echo "Membuat SSH tunnel: localhost:${LOCAL_PORT} → ${MASTER_HOST}:${REMOTE_PORT}"

# Cek apakah tunnel sudah berjalan
if ss -tlnp 2>/dev/null | grep -q ":${LOCAL_PORT} "; then
  echo "Tunnel sudah aktif di port ${LOCAL_PORT}"
  exit 0
fi

# Buat tunnel (autossh untuk reconnect otomatis)
if command -v autossh &>/dev/null; then
  autossh -M 0 -N -f \
    -o "ServerAliveInterval=30" \
    -o "ServerAliveCountMax=3" \
    -o "StrictHostKeyChecking=no" \
    -L "${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}" \
    "${SSH_USER}@${MASTER_HOST}"
  echo "Tunnel aktif (autossh). Reconnect otomatis jika terputus."
else
  ssh -fN \
    -o "ServerAliveInterval=30" \
    -o "ServerAliveCountMax=3" \
    -o "StrictHostKeyChecking=no" \
    -L "${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}" \
    "${SSH_USER}@${MASTER_HOST}"
  echo "Tunnel aktif (ssh). Install autossh untuk reconnect otomatis."
fi
