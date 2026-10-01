#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if [ -x .venv/bin/python ]; then
  .venv/bin/python agent.py --config config.json
else
  python3 agent.py --config config.json
fi
