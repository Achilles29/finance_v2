#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
RUNTIME_DIR="${A4_STATIC_RUNTIME_DIR:-/var/lib/finance-a4-static}"
RUNTIME_DIR="$(readlink -m -- "$RUNTIME_DIR")"
VENDOR_DIR="$RUNTIME_DIR/vendor"

if [[ "$RUNTIME_DIR" == "/" || "$RUNTIME_DIR/" == "$PROJECT_ROOT/"* ]]; then
    echo "ERROR: static runtime must be a narrow directory outside document root" >&2
    exit 1
fi
for command in php composer readlink; do
    command -v "$command" >/dev/null 2>&1 || { echo "ERROR: required command missing: $command" >&2; exit 1; }
done

php "$SCRIPT_DIR/a4_static_analysis_smoke.php" --source-only

umask 027
install -d -m 0750 -- "$RUNTIME_DIR" "$RUNTIME_DIR/tmp"
COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_VENDOR_DIR="$VENDOR_DIR" \
    composer install --working-dir="$PROJECT_ROOT" --no-interaction --no-scripts --prefer-dist --no-progress

A4_STATIC_RUNTIME_DIR="$RUNTIME_DIR" php "$SCRIPT_DIR/a4_static_analysis_smoke.php" --runtime-check-only
echo "A4 static runtime ready: $RUNTIME_DIR"
