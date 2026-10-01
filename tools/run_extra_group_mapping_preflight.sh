#!/usr/bin/env bash

set -euo pipefail

readonly script_dir="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly repo_root="$(cd -P -- "${script_dir}/.." && pwd -P)"
readonly sql_file="${script_dir}/sql/2026-09-03_extra_group_mapping_preflight.sql"

fail() {
    local status="$1"
    local message="$2"
    printf '%s\n' "$message" >&2
    exit "$status"
}

canonical_existing_path() {
    local candidate="$1"
    if command -v realpath >/dev/null 2>&1; then
        realpath -e -- "$candidate" 2>/dev/null
        return
    fi
    if command -v readlink >/dev/null 2>&1; then
        readlink -f -- "$candidate" 2>/dev/null
        return
    fi
    return 1
}

if (( $# != 0 )); then
    fail 64 'Batch 66 preflight does not accept arguments.'
fi

if [[ ! -f "$sql_file" || ! -r "$sql_file" ]]; then
    fail 64 'Batch 66 preflight SQL is unavailable.'
fi
if ! command -v realpath >/dev/null 2>&1 && ! command -v readlink >/dev/null 2>&1; then
    fail 64 'Batch 66 path canonicalization prerequisite is unavailable.'
fi

option_file_input="${MYSQL_DEFAULTS_EXTRA_FILE:-}"
if [[ -z "$option_file_input" || "$option_file_input" == *$'\n'* || "$option_file_input" == *$'\r'* ]]; then
    fail 66 'Batch 66 database option file is invalid.'
fi
if [[ ! -f "$option_file_input" || ! -r "$option_file_input" ]]; then
    fail 66 'Batch 66 database option file is invalid.'
fi
option_file="$(canonical_existing_path "$option_file_input")" ||
    fail 66 'Batch 66 database option file is invalid.'
if [[ "$option_file" == "$repo_root" || "$option_file" == "$repo_root/"* ]]; then
    fail 66 'Batch 66 database option file must be external.'
fi
readonly option_file

mysql_client_input="${MYSQL_CLIENT:-}"
mysql_client=''
if [[ -z "$mysql_client_input" ]]; then
    if command -v mysql >/dev/null 2>&1; then
        mysql_client="$(command -v mysql)"
    elif command -v mariadb >/dev/null 2>&1; then
        mysql_client="$(command -v mariadb)"
    else
        fail 127 'Batch 66 mysql/mariadb client is unavailable.'
    fi
elif [[ "$mysql_client_input" == 'mysql' || "$mysql_client_input" == 'mariadb' ]]; then
    if ! command -v "$mysql_client_input" >/dev/null 2>&1; then
        fail 127 'Batch 66 mysql/mariadb client is unavailable.'
    fi
    mysql_client="$(command -v "$mysql_client_input")"
elif [[ "$mysql_client_input" == /* && "$mysql_client_input" != *$'\n'* && "$mysql_client_input" != *$'\r'* ]]; then
    mysql_client="$(canonical_existing_path "$mysql_client_input")" ||
        fail 64 'Batch 66 configured database client is invalid.'
else
    fail 64 'Batch 66 configured database client is invalid.'
fi

mysql_client="$(canonical_existing_path "$mysql_client")" ||
    fail 127 'Batch 66 mysql/mariadb client is unavailable.'
if [[ ! -f "$mysql_client" || ! -x "$mysql_client" ]]; then
    fail 127 'Batch 66 mysql/mariadb client is unavailable.'
fi
readonly mysql_client

set +e
db_output="$({
    unset MYSQL_PWD MYSQL_DEFAULTS_EXTRA_FILE MYSQL_CLIENT
    "$mysql_client" \
        "--defaults-file=${option_file}" \
        --batch \
        --raw \
        --skip-column-names < "$sql_file" 2>/dev/null
})"
client_status=$?
set -e

if (( client_status != 0 )); then
    fail 69 'Batch 66 database connection or query failed.'
fi

declare -A expected_schema_checks=(
    [TABLE_PRESENCE]=5
    [ENGINE_INNODB]=5
    [REQUIRED_COLUMNS]=16
    [UNIQUE_MAP_PAIR]=1
    [UNIQUE_ITEM_PAIR]=1
    [FK_MAP_GROUP]=1
    [FK_MAP_PRODUCT]=1
    [FK_ITEM_GROUP]=1
    [FK_ITEM_EXTRA]=1
    [FK_MAP_GROUP_RULES]=1
    [FK_MAP_PRODUCT_RULES]=1
    [FK_ITEM_GROUP_RULES]=1
    [FK_ITEM_EXTRA_RULES]=1
)
declare -A expected_finding_checks=(
    [ORPHAN_MAP_GROUP]=1
    [ORPHAN_MAP_PRODUCT]=1
    [ORPHAN_ITEM_GROUP]=1
    [ORPHAN_ITEM_EXTRA]=1
    [DUPLICATE_MAP_PAIR]=1
    [DUPLICATE_ITEM_PAIR]=1
    [INACTIVE_MAP]=1
    [INACTIVE_ITEM]=1
    [DIVISION_MISMATCH]=1
)
declare -A schema_status=()
declare -A schema_actual=()
declare -A schema_expected=()
declare -A finding_count=()
declare -A detail_count=()
end_count=0
end_seen=0

is_db_integer() {
    [[ "$1" =~ ^(0|[1-9][0-9]{0,18})$ ]]
}

is_db_scalar() {
    [[ "$1" == 'NULL' ]] || is_db_integer "$1"
}

while IFS= read -r marker_line || [[ -n "$marker_line" ]]; do
    if (( end_seen != 0 )); then
        fail 69 'Batch 66 database output was invalid.'
    fi
    IFS=$'\t' read -r -a fields <<< "$marker_line"
    case "${fields[0]:-}" in
        B66_SCHEMA)
            if (( ${#fields[@]} != 5 )); then
                fail 69 'Batch 66 database output was invalid.'
            fi
            check="${fields[1]}"
            status="${fields[2]}"
            actual="${fields[3]}"
            expected="${fields[4]}"
            if [[ -z "${expected_schema_checks[$check]:-}" || -n "${schema_status[$check]:-}" ]]; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            if [[ "$status" != 'PASS' && "$status" != 'FAIL' ]]; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            if ! is_db_integer "$actual" || ! is_db_integer "$expected"; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            if [[ "$expected" != "${expected_schema_checks[$check]}" ]]; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            schema_status[$check]="$status"
            schema_actual[$check]="$actual"
            schema_expected[$check]="$expected"
            ;;
        B66_FINDING)
            if (( ${#fields[@]} != 3 )); then
                fail 69 'Batch 66 database output was invalid.'
            fi
            category="${fields[1]}"
            count="${fields[2]}"
            if [[ -z "${expected_finding_checks[$category]:-}" || -n "${finding_count[$category]:-}" ]]; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            if ! is_db_integer "$count"; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            finding_count[$category]="$count"
            ;;
        B66_ROW)
            if (( ${#fields[@]} != 9 )); then
                fail 69 'Batch 66 database output was invalid.'
            fi
            category="${fields[1]}"
            if [[ -z "${expected_finding_checks[$category]:-}" ]]; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            for field_index in 2 3 4 5 6 7 8; do
                if ! is_db_scalar "${fields[$field_index]}"; then
                    fail 69 'Batch 66 database output was invalid.'
                fi
            done
            detail_count[$category]="$(( ${detail_count[$category]:-0} + 1 ))"
            if (( detail_count[$category] > 50 )); then
                fail 69 'Batch 66 database output was invalid.'
            fi
            ;;
        B66_END)
            if (( ${#fields[@]} != 2 )) || [[ "${fields[1]}" != 'OK' ]]; then
                fail 69 'Batch 66 database output was invalid.'
            fi
            end_count=$((end_count + 1))
            end_seen=1
            ;;
        *)
            fail 69 'Batch 66 database output was invalid.'
            ;;
    esac
done <<< "$db_output"

if (( end_count != 1 )); then
    fail 69 'Batch 66 database output was incomplete.'
fi
for check in "${!expected_schema_checks[@]}"; do
    if [[ -z "${schema_status[$check]:-}" ]]; then
        fail 69 'Batch 66 database output was incomplete.'
    fi
done
for category in "${!expected_finding_checks[@]}"; do
    if [[ -z "${finding_count[$category]:-}" ]]; then
        fail 69 'Batch 66 database output was incomplete.'
    fi
    category_detail_count="${detail_count[$category]:-0}"
    category_finding_count="${finding_count[$category]}"
    if (( ${#category_finding_count} < 3 )) && (( category_detail_count > category_finding_count )); then
        fail 69 'Batch 66 database output was inconsistent.'
    fi
done

schema_failed=0
for check in "${!expected_schema_checks[@]}"; do
    if [[ "${schema_status[$check]}" == 'FAIL' ]]; then
        printf 'B66_SCHEMA_FINDING\t%s\t%s\t%s\n' \
            "$check" "${schema_actual[$check]}" "${schema_expected[$check]}"
        schema_failed=1
    fi
done
if (( schema_failed != 0 )); then
    exit 10
fi

has_integrity_finding=0
has_policy_finding=0
for category in \
    ORPHAN_MAP_GROUP ORPHAN_MAP_PRODUCT ORPHAN_ITEM_GROUP ORPHAN_ITEM_EXTRA \
    DUPLICATE_MAP_PAIR DUPLICATE_ITEM_PAIR; do
    if [[ "${finding_count[$category]}" != '0' ]]; then
        printf 'B66_DATA_FINDING\t%s\t%s\n' "$category" "${finding_count[$category]}"
        has_integrity_finding=1
    fi
done
for category in INACTIVE_MAP INACTIVE_ITEM DIVISION_MISMATCH; do
    if [[ "${finding_count[$category]}" != '0' ]]; then
        printf 'B66_DATA_FINDING\t%s\t%s\n' "$category" "${finding_count[$category]}"
        has_policy_finding=1
    fi
done

if (( has_integrity_finding != 0 )); then
    exit 11
fi
if (( has_policy_finding != 0 )); then
    exit 12
fi

printf 'B66_RESULT\tCLEAN\n'
exit 0
