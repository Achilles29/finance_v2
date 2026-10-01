#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if [ -x .venv/bin/python ]; then
  .venv/bin/python detect_printers.py > printer_detect.json
else
  python3 detect_printers.py > printer_detect.json
fi
echo "Hasil deteksi disimpan di printer_detect.json"
cat printer_detect.json
