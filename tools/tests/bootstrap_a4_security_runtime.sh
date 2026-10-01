#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
LOCK_FILE="$PROJECT_ROOT/tools/security/toolchain.lock.json"
RUNTIME_DIR="${A4_SECURITY_RUNTIME_DIR:-/var/lib/finance-a4-security}"
RUNTIME_DIR="$(readlink -m -- "$RUNTIME_DIR")"
TEMP_FILES=()
REFRESH=0

for argument in "$@"; do
    case "$argument" in
        --refresh) REFRESH=1 ;;
        *) echo "Usage: $0 [--refresh]" >&2; exit 2 ;;
    esac
done

cleanup() {
    local temporary
    for temporary in "${TEMP_FILES[@]:-}"; do
        if [[ -n "$temporary" && -f "$temporary" ]]; then
            rm -f -- "$temporary"
        fi
    done
}
trap cleanup EXIT

if [[ "$RUNTIME_DIR" == "/" || "$RUNTIME_DIR/" == "$PROJECT_ROOT/"* ]]; then
        echo "ERROR: security runtime must be a narrow directory outside document root" >&2
        exit 1
fi

for command in php curl flock sha256sum unzip; do
    command -v "$command" >/dev/null 2>&1 || { echo "ERROR: required command missing: $command" >&2; exit 1; }
done

php "$SCRIPT_DIR/a4_dependency_vulnerability_smoke.php" --source-only

mapfile -t TOOL < <(php -r '
$c=json_decode((string)file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);
echo $c["osv_scanner"]["url"],"\n",$c["osv_scanner"]["sha256"],"\n",$c["osv_scanner"]["version"],"\n",$c["osv_scanner"]["relative_path"],"\n";
' "$LOCK_FILE")
[[ "${#TOOL[@]}" -eq 4 ]] || { echo "ERROR: invalid toolchain lock" >&2; exit 1; }

umask 027
install -d -m 0750 -- "$RUNTIME_DIR/bin" "$RUNTIME_DIR/cache/osv-scalibr"
RUNTIME_LOCK="$RUNTIME_DIR/runtime.lock"
if [[ ! -e "$RUNTIME_LOCK" ]]; then
    install -m 0640 /dev/null "$RUNTIME_LOCK"
fi
if [[ ! -f "$RUNTIME_LOCK" || -L "$RUNTIME_LOCK" ]]; then
    echo "ERROR: invalid security runtime lock" >&2
    exit 1
fi
TOOL_TARGET="$RUNTIME_DIR/${TOOL[3]}"
if [[ ! -x "$TOOL_TARGET" || "$(sha256sum "$TOOL_TARGET" | awk '{print $1}')" != "${TOOL[1]}" ]]; then
    TOOL_TEMP="$(mktemp "$RUNTIME_DIR/bin/.osv-scanner.XXXXXX")"
    TEMP_FILES+=("$TOOL_TEMP")
    curl --fail --location --silent --show-error --proto '=https' --tlsv1.2 \
        --connect-timeout 15 --max-time 300 --retry 2 --output "$TOOL_TEMP" "${TOOL[0]}"
    [[ "$(sha256sum "$TOOL_TEMP" | awk '{print $1}')" == "${TOOL[1]}" ]] || {
        echo "ERROR: OSV-Scanner checksum mismatch" >&2
        exit 1
    }
    chmod 0750 "$TOOL_TEMP"
    "$TOOL_TEMP" --version 2>&1 | grep -Eq "(^|[^0-9])${TOOL[2]//./\.}([^0-9]|$)" || {
        echo "ERROR: OSV-Scanner version mismatch" >&2
        exit 1
    }
    mv -f -- "$TOOL_TEMP" "$TOOL_TARGET"
    TEMP_FILES=()
fi

if [[ "$REFRESH" -eq 0 ]] && A4_SECURITY_RUNTIME_DIR="$RUNTIME_DIR" \
    php "$SCRIPT_DIR/a4_dependency_vulnerability_smoke.php" --runtime-only >/dev/null 2>&1; then
    echo "A4 security runtime already current: $RUNTIME_DIR"
    exit 0
fi

exec 9<>"$RUNTIME_LOCK"
flock -x 9

mapfile -t DATABASES < <(php -r '
$c=json_decode((string)file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);
foreach($c["offline_databases"]["ecosystems"] as $d){echo $d["name"],"\t",$d["url"],"\t",$d["relative_path"],"\n";}
' "$LOCK_FILE")
[[ "${#DATABASES[@]}" -eq 3 ]] || { echo "ERROR: invalid database lock" >&2; exit 1; }

DB_NAMES=()
DB_TARGETS=()
DB_TEMPS=()
DB_HASHES=()
for row in "${DATABASES[@]}"; do
    IFS=$'\t' read -r ecosystem url relative_path <<< "$row"
    [[ "$relative_path" == "osv-scalibr/$ecosystem/all.zip" ]] || {
        echo "ERROR: unsafe offline database path" >&2
        exit 1
    }
    target="$RUNTIME_DIR/cache/$relative_path"
    install -d -m 0750 -- "$(dirname -- "$target")"
    temporary="$(mktemp "$(dirname -- "$target")/.all.zip.XXXXXX")"
    TEMP_FILES+=("$temporary")
    curl --fail --location --silent --show-error --proto '=https' --tlsv1.2 \
        --connect-timeout 15 --max-time 600 --retry 2 --output "$temporary" "$url"
    [[ -s "$temporary" ]] && unzip -tqq "$temporary" || {
        echo "ERROR: invalid offline database archive for $ecosystem" >&2
        exit 1
    }
    DB_NAMES+=("$ecosystem")
    DB_TARGETS+=("$target")
    DB_TEMPS+=("$temporary")
    DB_HASHES+=("$(sha256sum "$temporary" | awk '{print $1}')")
done

for index in "${!DB_TEMPS[@]}"; do
    mv -f -- "${DB_TEMPS[$index]}" "${DB_TARGETS[$index]}"
done
TEMP_FILES=()

SNAPSHOT="$RUNTIME_DIR/osv-db-snapshot.json"
SNAPSHOT_TEMP="$(mktemp "$RUNTIME_DIR/.osv-db-snapshot.XXXXXX")"
TEMP_FILES+=("$SNAPSHOT_TEMP")
php -r '
$output=$argv[1]; array_shift($argv); array_shift($argv); $entries=[];
foreach(array_chunk($argv,2) as $pair){$entries[]=["name"=>$pair[0],"sha256"=>$pair[1]];}
$snapshot=["schema"=>"finance.a4-osv-db-snapshot","schema_version"=>1,"created_at"=>gmdate("c"),"created_at_epoch"=>time(),"databases"=>$entries];
$json=json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(file_put_contents($output,$json,LOCK_EX)!==strlen($json)){fwrite(STDERR,"snapshot write failed\n");exit(1);}
' "$SNAPSHOT_TEMP" "${DB_NAMES[0]}" "${DB_HASHES[0]}" "${DB_NAMES[1]}" "${DB_HASHES[1]}" "${DB_NAMES[2]}" "${DB_HASHES[2]}"
chmod 0640 "$SNAPSHOT_TEMP"
mv -f -- "$SNAPSHOT_TEMP" "$SNAPSHOT"
TEMP_FILES=()

echo "A4 security runtime ready: $RUNTIME_DIR"
