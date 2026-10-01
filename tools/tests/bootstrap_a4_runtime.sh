#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
RUNTIME_DIR="${A4_RUNTIME_DIR:-/var/lib/finance-a4-runtime}"
RUNTIME_DIR="$(readlink -m -- "$RUNTIME_DIR")"
VENV_DIR="$RUNTIME_DIR/printer-venv"
REQUIREMENTS="$PROJECT_ROOT/tools/pos_printer_agent/requirements.txt"

case "$RUNTIME_DIR/" in
    "$PROJECT_ROOT/"*)
        echo "ERROR: A4_RUNTIME_DIR must be outside document root: $PROJECT_ROOT" >&2
        exit 1
        ;;
esac

if [[ ! -r "$REQUIREMENTS" ]]; then
    echo "ERROR: requirements file is not readable: $REQUIREMENTS" >&2
    exit 1
fi
if [[ ! -x /usr/bin/python3 ]]; then
    echo "ERROR: /usr/bin/python3 is missing or not executable" >&2
    exit 1
fi
if [[ ! -x /usr/bin/google-chrome-stable ]]; then
    echo "ERROR: /usr/bin/google-chrome-stable is missing or not executable" >&2
    exit 1
fi

umask 027
install -d -m 0750 -- "$RUNTIME_DIR"
if [[ ! -x "$VENV_DIR/bin/python" ]]; then
    /usr/bin/python3 -m venv "$VENV_DIR"
fi

"$VENV_DIR/bin/python" -m pip install --disable-pip-version-check --require-hashes --requirement "$REQUIREMENTS"
"$VENV_DIR/bin/python" -c 'import flask, serial, PIL, qrcode'
/usr/bin/google-chrome-stable --version
"$VENV_DIR/bin/python" --version
echo "A4 runtime ready: $RUNTIME_DIR"
