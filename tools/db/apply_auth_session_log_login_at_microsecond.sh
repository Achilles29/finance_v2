#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly script_dir
readonly migration_file="${script_dir}/../../sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql"

mysql_client="${AUTH_SESSION_LOG_DB_CLIENT:-}"
if [[ -z "$mysql_client" ]]; then
    if command -v mariadb >/dev/null 2>&1; then
        mysql_client='mariadb'
    elif command -v mysql >/dev/null 2>&1; then
        mysql_client='mysql'
    else
        printf '%s\n' 'Batch 53.2 migration client is unavailable.' >&2
        exit 127
    fi
fi

if ! command -v "$mysql_client" >/dev/null 2>&1; then
    printf '%s\n' 'Batch 53.2 configured migration client is unavailable.' >&2
    exit 127
fi

client_command=("$mysql_client")
if [[ -n "${MYSQL_DEFAULTS_EXTRA_FILE:-}" ]]; then
    if [[ ! -r "$MYSQL_DEFAULTS_EXTRA_FILE" ]]; then
        printf '%s\n' 'Batch 53.2 client option file is not readable.' >&2
        exit 66
    fi
    client_command+=("--defaults-extra-file=${MYSQL_DEFAULTS_EXTRA_FILE}")
fi

# mysql and mariadb process DELIMITER client commands while reading batch input.
client_command+=(--batch)
readonly -a client_command

cleanup_procedure() {
    local migration_status=$?
    local cleanup_status

    trap - EXIT
    set +e
    printf '%s\n' 'DROP PROCEDURE IF EXISTS sp_auth_session_log_login_at_usec_20260903b;' | "${client_command[@]}"
    cleanup_status=$?
    set -e

    if (( migration_status != 0 )); then
        if (( cleanup_status != 0 )); then
            printf '%s\n' 'Batch 53.2 migration and routine cleanup both failed.' >&2
        fi
        exit "$migration_status"
    fi

    if (( cleanup_status != 0 )); then
        printf '%s\n' 'Batch 53.2 routine cleanup failed.' >&2
        exit "$cleanup_status"
    fi

    exit 0
}

trap cleanup_procedure EXIT
"${client_command[@]}" < "${migration_file}"
