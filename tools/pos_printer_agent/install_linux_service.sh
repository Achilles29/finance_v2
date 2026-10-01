#!/usr/bin/env bash
set -euo pipefail

# Install from the already-extracted agent directory. The service deliberately
# runs as the current non-root operator so Bluetooth/USB permissions remain
# visible and config.json stays owned by that operator.
if [ "$(id -u)" -eq 0 ]; then
  echo "Jalankan script ini sebagai user operator (bukan root); script akan meminta sudo bila perlu." >&2
  exit 2
fi

AGENT_DIR="$(cd "$(dirname "$0")" && pwd)"
SERVICE_USER="$(id -un)"
SERVICE_NAME="finance-pos-printer-agent"
PYTHON_BIN="$AGENT_DIR/.venv/bin/python"

if [ ! -x "$PYTHON_BIN" ]; then
  echo "Python virtual environment belum siap: $PYTHON_BIN" >&2
  exit 2
fi
if [ ! -f "$AGENT_DIR/config.json" ]; then
  echo "config.json belum ada. Unduh config.json dari Finance terlebih dahulu." >&2
  exit 2
fi

sudo tee "/etc/systemd/system/${SERVICE_NAME}.service" >/dev/null <<EOF
[Unit]
Description=Finance POS Printer Agent
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=${SERVICE_USER}
WorkingDirectory=${AGENT_DIR}
ExecStart=${PYTHON_BIN} ${AGENT_DIR}/agent.py --config ${AGENT_DIR}/config.json
Restart=always
RestartSec=5
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now "$SERVICE_NAME"
sudo systemctl --no-pager --full status "$SERVICE_NAME"
echo "OK: service aktif. Cek log dengan: journalctl -u $SERVICE_NAME -f"
