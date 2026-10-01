#!/usr/bin/env bash
set -euo pipefail

SERVICE_NAME="finance-pos-printer-agent"
sudo systemctl disable --now "$SERVICE_NAME" 2>/dev/null || true
sudo rm -f "/etc/systemd/system/${SERVICE_NAME}.service"
sudo systemctl daemon-reload
echo "OK: service dihapus. Folder agent dan config.json tidak dihapus."
