#!/usr/bin/env bash

set -euo pipefail

readonly test_dir="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly repo_root="$(cd -P -- "${test_dir}/../.." && pwd -P)"
readonly wrapper="${repo_root}/tools/run_extra_group_mapping_preflight.sh"
readonly sql_file="${repo_root}/tools/sql/2026-09-03_extra_group_mapping_preflight.sql"
readonly runbook="${repo_root}/docs/2026-09-03_extra_group_mapping_preflight_runbook.md"

checks=0

pass() {
    checks=$((checks + 1))
}

assert_eq() {
    local expected="$1"
    local actual="$2"
    local label="$3"
    if [[ "$actual" != "$expected" ]]; then
        printf 'FAIL: %s (expected=%s actual=%s)\n' "$label" "$expected" "$actual" >&2
        exit 1
    fi
    pass
}

assert_contains() {
    local file="$1"
    local pattern="$2"
    local label="$3"
    if ! grep -Eq -- "$pattern" "$file"; then
        printf 'FAIL: %s\n' "$label" >&2
        exit 1
    fi
    pass
}

assert_not_contains() {
    local file="$1"
    local pattern="$2"
    local label="$3"
    if grep -Eiq -- "$pattern" "$file"; then
        printf 'FAIL: %s\n' "$label" >&2
        exit 1
    fi
    pass
}

for required_file in "$wrapper" "$sql_file" "$runbook"; do
    if [[ ! -f "$required_file" ]]; then
        printf 'FAIL: required Batch 66 file is missing.\n' >&2
        exit 1
    fi
    pass
done

assert_contains "$wrapper" '^set -euo pipefail$' 'wrapper enables bash strict mode'
assert_not_contains "$wrapper" 'application/config/database\.php|database\.php' 'wrapper never reads the application database config'
assert_not_contains "$wrapper" '--password' 'wrapper contains no long password option'
assert_not_contains "$wrapper" '--defaults-extra-file' 'wrapper does not allow global option files before the external option file'
assert_contains "$wrapper" '"--defaults-file=\$\{option_file\}"' 'wrapper exclusively selects the external option file'
assert_contains "$wrapper" 'unset MYSQL_PWD MYSQL_DEFAULTS_EXTRA_FILE MYSQL_CLIENT' 'wrapper removes inherited client credential/configuration variables'
assert_contains "$wrapper" '\[REQUIRED_COLUMNS\]=16' 'wrapper pins the required-column expected value'
for rule_check in FK_MAP_GROUP_RULES FK_MAP_PRODUCT_RULES FK_ITEM_GROUP_RULES FK_ITEM_EXTRA_RULES; do
    assert_contains "$wrapper" "\[${rule_check}\]=1" "wrapper allow-lists ${rule_check} with its expected value"
done

tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/b66-extra-group-smoke.XXXXXX")"
readonly tmp_dir
cleanup() {
    rm -rf -- "$tmp_dir"
}
trap cleanup EXIT

sql_without_comments="${tmp_dir}/sql-without-comments.sql"
sed '/^[[:space:]]*--/d' "$sql_file" > "$sql_without_comments"

assert_contains "$sql_without_comments" 'START[[:space:]]+TRANSACTION[[:space:]]+READ[[:space:]]+ONLY[[:space:]]*;' 'SQL starts a read-only transaction'
assert_contains "$sql_without_comments" 'COMMIT[[:space:]]*;' 'SQL commits the read-only transaction'
assert_not_contains "$sql_without_comments" '(^|[^[:alnum:]_])(INSERT|UPDATE|DELETE|REPLACE|MERGE|CREATE|ALTER|DROP|TRUNCATE|RENAME|CALL|EXECUTE|PREPARE|LOCK|UNLOCK|GRANT|REVOKE|LOAD|HANDLER|DO|SET)([^[:alnum:]_]|$)' 'SQL contains no mutating or administrative statement'
assert_not_contains "$sql_without_comments" 'TEMPORARY[[:space:]]+TABLE' 'SQL contains no temporary table'
assert_not_contains "$sql_without_comments" 'source_kind' 'SQL contains no source_kind policy'

select_count="$(grep -Eic '^[[:space:]]*SELECT([[:space:]]|$)' "$sql_without_comments")"
if (( select_count < 20 )); then
    printf 'FAIL: SQL does not contain the expected SELECT-only reports.\n' >&2
    exit 1
fi
pass

for marker in B66_SCHEMA B66_FINDING B66_ROW B66_END; do
    assert_contains "$sql_without_comments" "'${marker}'" "SQL contains ${marker} markers"
done
for table in mst_product_extra_map mst_extra_group_item mst_product mst_extra_group mst_extra; do
    assert_contains "$sql_without_comments" "(^|[^[:alnum:]_])${table}([^[:alnum:]_]|$)" "SQL reads ${table}"
done
for schema_check in TABLE_PRESENCE ENGINE_INNODB REQUIRED_COLUMNS UNIQUE_MAP_PAIR UNIQUE_ITEM_PAIR FK_MAP_GROUP FK_MAP_PRODUCT FK_ITEM_GROUP FK_ITEM_EXTRA FK_MAP_GROUP_RULES FK_MAP_PRODUCT_RULES FK_ITEM_GROUP_RULES FK_ITEM_EXTRA_RULES; do
    assert_contains "$sql_without_comments" "'${schema_check}'" "SQL reports ${schema_check}"
done
for finding in ORPHAN_MAP_GROUP ORPHAN_MAP_PRODUCT ORPHAN_ITEM_GROUP ORPHAN_ITEM_EXTRA DUPLICATE_MAP_PAIR DUPLICATE_ITEM_PAIR INACTIVE_MAP INACTIVE_ITEM DIVISION_MISMATCH; do
    assert_contains "$sql_without_comments" "'${finding}'" "SQL reports ${finding}"
done
assert_contains "$sql_without_comments" 'extra_group\.product_division_id[[:space:]]+IS[[:space:]]+NOT[[:space:]]+NULL' 'division mismatch allows NULL group division'
assert_contains "$sql_without_comments" "column_name[[:space:]]+IN[[:space:]]*\('id',[[:space:]]*'extra_group_id',[[:space:]]*'product_id',[[:space:]]*'sort_order'\)" 'map required columns include sort_order'
assert_contains "$sql_without_comments" "column_name[[:space:]]+IN[[:space:]]*\('id',[[:space:]]*'extra_group_id',[[:space:]]*'extra_id',[[:space:]]*'sort_order'\)" 'item required columns include sort_order'
assert_contains "$sql_without_comments" "IF\(COUNT\(\*\)[[:space:]]*=[[:space:]]*16,[[:space:]]*'PASS',[[:space:]]*'FAIL'\)" 'required column contract expects exactly sixteen columns'
for constraint_name in fk_mst_product_extra_map_group fk_mst_product_extra_map_product fk_mst_extra_group_item_group fk_mst_extra_group_item_extra; do
    assert_contains "$sql_without_comments" "constraint_name[[:space:]]*=[[:space:]]*'${constraint_name}'" "SQL pins FK identity ${constraint_name}"
done
assert_contains "$sql_without_comments" 'information_schema\.referential_constraints' 'SQL reads referential action metadata'
assert_contains "$sql_without_comments" "UPPER\(reference_rule\.delete_rule\)[[:space:]]*=[[:space:]]*'RESTRICT'" 'SQL requires DELETE RESTRICT'
assert_contains "$sql_without_comments" "UPPER\(reference_rule\.update_rule\)[[:space:]]*=[[:space:]]*'RESTRICT'" 'SQL requires UPDATE RESTRICT'

allowed_sources="${tmp_dir}/allowed-sources.txt"
grep -Eio '(FROM|JOIN)[[:space:]]+([`[:alnum:]_.]+)' "$sql_without_comments" \
    | awk '{print $2}' \
    | tr -d '`' \
    | LC_ALL=C sort -u > "$allowed_sources"
while IFS= read -r source_name; do
    case "$source_name" in
        information_schema.tables|information_schema.columns|information_schema.statistics|information_schema.key_column_usage|information_schema.referential_constraints|information_schema.schemata|mst_product_extra_map|mst_extra_group_item|mst_product|mst_extra_group|mst_extra)
            ;;
        *)
            printf 'FAIL: SQL reads an unexpected source: %s\n' "$source_name" >&2
            exit 1
            ;;
    esac
done < "$allowed_sources"
pass

limit_count="$(grep -Ec '^[[:space:]]*LIMIT[[:space:]]+50[[:space:]]*;' "$sql_without_comments")"
assert_eq '9' "$limit_count" 'all nine detail reports are bounded'

fake_client="${tmp_dir}/fake-mysql"
option_file="${tmp_dir}/client.cnf"
arg_log="${tmp_dir}/args.log"
sql_capture="${tmp_dir}/sql.capture"
output_file="${tmp_dir}/stdout.log"
error_file="${tmp_dir}/stderr.log"

printf '%s\n' '[client]' 'user=preflight_reader' 'password=OPTION_FILE_SECRET_MARKER' 'database=finance_clone' > "$option_file"
chmod 600 "$option_file"

cat > "$fake_client" <<'FAKE_CLIENT'
#!/usr/bin/env bash
set -euo pipefail

: "${FAKE_ARG_LOG:?}"
: "${FAKE_SQL_CAPTURE:?}"
: "${FAKE_MODE:?}"

printf '%s\n' "$@" > "$FAKE_ARG_LOG"
if [[ "${1:-}" != --defaults-file=* ]]; then
    printf '%s\n' 'DB_SECRET_DETAIL global defaults were not disabled' >&2
    exit 93
fi
if [[ -n "${MYSQL_PWD+x}" ]]; then
    printf '%s\n' 'DB_SECRET_DETAIL inherited MYSQL_PWD' >&2
    exit 90
fi
if [[ -n "${MYSQL_DEFAULTS_EXTRA_FILE+x}" || -n "${MYSQL_CLIENT+x}" ]]; then
    printf '%s\n' 'DB_SECRET_DETAIL inherited wrapper configuration' >&2
    exit 92
fi
for argument in "$@"; do
    case "$argument" in
        --defaults-extra-file|--defaults-extra-file=*)
            printf '%s\n' 'DB_SECRET_DETAIL extra defaults file argument' >&2
            exit 94
            ;;
        --password|--password=*|-p*)
            printf '%s\n' 'DB_SECRET_DETAIL password argument' >&2
            exit 91
            ;;
    esac
done

tee "$FAKE_SQL_CAPTURE" >/dev/null
printf '%s\n' 'DB_SECRET_DETAIL fake diagnostic' >&2

if [[ "$FAKE_MODE" == 'client_error' ]]; then
    exit 42
fi
if [[ "$FAKE_MODE" == 'malformed' ]]; then
    printf '%s\n' $'UNEXPECTED\tDB_SECRET_OUTPUT_MARKER'
    exit 0
fi

schema_engine_status='PASS'
schema_engine_actual='5'
schema_required_columns_expected='16'
schema_fk_map_group_rules_status='PASS'
schema_fk_map_group_rules_actual='1'
schema_fk_map_group_delete_rule='RESTRICT'
if [[ "$FAKE_MODE" == 'schema' ]]; then
    schema_engine_status='FAIL'
    schema_engine_actual='4'
fi
if [[ "$FAKE_MODE" == 'cascade' ]]; then
    schema_fk_map_group_delete_rule='CASCADE'
fi
if [[ "$FAKE_MODE" == 'expected_mismatch' ]]; then
    schema_required_columns_expected='14'
fi
if [[ "$schema_fk_map_group_delete_rule" != 'RESTRICT' ]]; then
    schema_fk_map_group_rules_status='FAIL'
    schema_fk_map_group_rules_actual='0'
fi

printf '%s\n' \
    $'B66_SCHEMA\tTABLE_PRESENCE\tPASS\t5\t5' \
    "B66_SCHEMA"$'\t'"ENGINE_INNODB"$'\t'"${schema_engine_status}"$'\t'"${schema_engine_actual}"$'\t5' \
    "B66_SCHEMA"$'\t'"REQUIRED_COLUMNS"$'\t'"PASS"$'\t'"16"$'\t'"${schema_required_columns_expected}" \
    $'B66_SCHEMA\tUNIQUE_MAP_PAIR\tPASS\t1\t1' \
    $'B66_SCHEMA\tUNIQUE_ITEM_PAIR\tPASS\t1\t1' \
    $'B66_SCHEMA\tFK_MAP_GROUP\tPASS\t1\t1' \
    $'B66_SCHEMA\tFK_MAP_PRODUCT\tPASS\t1\t1' \
    $'B66_SCHEMA\tFK_ITEM_GROUP\tPASS\t1\t1' \
    $'B66_SCHEMA\tFK_ITEM_EXTRA\tPASS\t1\t1' \
    "B66_SCHEMA"$'\t'"FK_MAP_GROUP_RULES"$'\t'"${schema_fk_map_group_rules_status}"$'\t'"${schema_fk_map_group_rules_actual}"$'\t1' \
    $'B66_SCHEMA\tFK_MAP_PRODUCT_RULES\tPASS\t1\t1' \
    $'B66_SCHEMA\tFK_ITEM_GROUP_RULES\tPASS\t1\t1' \
    $'B66_SCHEMA\tFK_ITEM_EXTRA_RULES\tPASS\t1\t1'

if [[ "$FAKE_MODE" == 'early_end' ]]; then
    printf '%s\n' $'B66_END\tOK'
fi

orphan_count='0'
inactive_count='0'
if [[ "$FAKE_MODE" == 'integrity' || "$FAKE_MODE" == 'schema' ]]; then
    orphan_count='2'
fi
if [[ "$FAKE_MODE" == 'count_detail_inconsistent' ]]; then
    orphan_count='1'
fi
if [[ "$FAKE_MODE" == 'policy' ]]; then
    inactive_count='1'
fi

printf '%s\n' \
    "B66_FINDING"$'\t'"ORPHAN_MAP_GROUP"$'\t'"${orphan_count}" \
    $'B66_FINDING\tORPHAN_MAP_PRODUCT\t0' \
    $'B66_FINDING\tORPHAN_ITEM_GROUP\t0' \
    $'B66_FINDING\tORPHAN_ITEM_EXTRA\t0' \
    $'B66_FINDING\tDUPLICATE_MAP_PAIR\t0' \
    $'B66_FINDING\tDUPLICATE_ITEM_PAIR\t0' \
    "B66_FINDING"$'\t'"INACTIVE_MAP"$'\t'"${inactive_count}" \
    $'B66_FINDING\tINACTIVE_ITEM\t0' \
    $'B66_FINDING\tDIVISION_MISMATCH\t0'

if [[ "$FAKE_MODE" == 'integrity' ]]; then
    printf '%s\n' $'B66_ROW\tORPHAN_MAP_GROUP\t101\t202\t303\tNULL\tNULL\tNULL\tNULL'
fi
if [[ "$FAKE_MODE" == 'count_detail_inconsistent' ]]; then
    printf '%s\n' \
        $'B66_ROW\tORPHAN_MAP_GROUP\t101\t202\t303\tNULL\tNULL\tNULL\tNULL' \
        $'B66_ROW\tORPHAN_MAP_GROUP\t102\t202\t304\tNULL\tNULL\tNULL\tNULL'
fi
if [[ "$FAKE_MODE" == 'policy' ]]; then
    printf '%s\n' $'B66_ROW\tINACTIVE_MAP\t101\t202\t303\t0\t1\t7\t7'
fi
if [[ "$FAKE_MODE" != 'early_end' ]]; then
    printf '%s\n' $'B66_END\tOK'
fi
FAKE_CLIENT
chmod 700 "$fake_client"

repo_names_before="${tmp_dir}/repo-names-before.txt"
repo_names_after="${tmp_dir}/repo-names-after.txt"
batch_hash_before="${tmp_dir}/batch-hash-before.txt"
batch_hash_after="${tmp_dir}/batch-hash-after.txt"
find "$repo_root" -path "$repo_root/.git" -prune -o -type f -printf '%P\n' | LC_ALL=C sort > "$repo_names_before"
sha256sum "$wrapper" "$sql_file" "$runbook" "${BASH_SOURCE[0]}" > "$batch_hash_before"

run_case() {
    local expected_status="$1"
    local mode="$2"
    shift 2
    : > "$output_file"
    : > "$error_file"
    : > "$arg_log"
    : > "$sql_capture"
    set +e
    FAKE_MODE="$mode" \
    FAKE_ARG_LOG="$arg_log" \
    FAKE_SQL_CAPTURE="$sql_capture" \
    MYSQL_PWD='INHERITED_SECRET_MARKER' \
    MYSQL_DEFAULTS_EXTRA_FILE="$option_file" \
    MYSQL_CLIENT="$fake_client" \
        "$wrapper" "$@" > "$output_file" 2> "$error_file"
    actual_status=$?
    set -e
    assert_eq "$expected_status" "$actual_status" "exit mapping for ${mode}"
}

run_case 0 clean
assert_contains "$output_file" $'^B66_RESULT\tCLEAN$' 'clean result is parsable'
assert_not_contains "$output_file" 'SECRET|password|finance_clone|preflight_reader' 'clean stdout contains no credential detail'
assert_not_contains "$error_file" 'DB_SECRET_DETAIL|INHERITED_SECRET' 'database stderr is redacted on success'

mapfile -t client_args < "$arg_log"
assert_eq '4' "${#client_args[@]}" 'client receives exactly four options'
assert_eq "--defaults-file=${option_file}" "${client_args[0]}" 'defaults-file is the first client option and excludes global option files'
assert_eq '--batch' "${client_args[1]}" 'client uses batch mode'
assert_eq '--raw' "${client_args[2]}" 'client uses raw mode'
assert_eq '--skip-column-names' "${client_args[3]}" 'client suppresses column names'
assert_not_contains "$arg_log" '--defaults-extra-file' 'client receives no defaults-extra-file option'
assert_not_contains "$arg_log" '(^|[[:space:]])(--password|-p)|OPTION_FILE_SECRET_MARKER|INHERITED_SECRET_MARKER' 'no password or credential value is forwarded as an argument'
if ! cmp -s "$sql_file" "$sql_capture"; then
    printf 'FAIL: wrapper did not feed the fixed Batch 66 SQL.\n' >&2
    exit 1
fi
pass

run_case 10 schema
assert_contains "$output_file" $'^B66_SCHEMA_FINDING\tENGINE_INNODB\t4\t5$' 'schema finding is sanitized and parsable'
assert_not_contains "$output_file" '^B66_DATA_FINDING' 'schema failure exits before printing parsed data findings'
assert_not_contains "$output_file" 'SECRET|password|finance_clone|preflight_reader' 'schema stdout contains no credential detail'

run_case 10 cascade
assert_contains "$output_file" $'^B66_SCHEMA_FINDING\tFK_MAP_GROUP_RULES\t0\t1$' 'CASCADE referential action fails the schema contract'
assert_not_contains "$output_file" '^B66_DATA_FINDING' 'referential-rule failure prints no data findings'

run_case 11 integrity
assert_contains "$output_file" $'^B66_DATA_FINDING\tORPHAN_MAP_GROUP\t2$' 'integrity finding maps to exit 11'

run_case 12 policy
assert_contains "$output_file" $'^B66_DATA_FINDING\tINACTIVE_MAP\t1$' 'inactive finding maps to exit 12'

run_case 69 client_error
assert_contains "$error_file" '^Batch 66 database connection or query failed\.$' 'query failure uses a generic error'
assert_not_contains "$error_file" 'DB_SECRET_DETAIL|INHERITED_SECRET|OPTION_FILE_SECRET' 'query stderr detail is redacted'
assert_not_contains "$output_file" 'DB_SECRET_DETAIL|SECRET' 'query failure leaks no stdout detail'

run_case 69 malformed
assert_contains "$error_file" '^Batch 66 database output was invalid\.$' 'malformed output uses a generic error'
assert_not_contains "$error_file" 'DB_SECRET_OUTPUT_MARKER|DB_SECRET_DETAIL' 'malformed output detail is redacted'

run_case 69 expected_mismatch
assert_contains "$error_file" '^Batch 66 database output was invalid\.$' 'unexpected schema expected value is rejected generically'

run_case 69 early_end
assert_contains "$error_file" '^Batch 66 database output was invalid\.$' 'marker after an early END is rejected generically'

run_case 69 count_detail_inconsistent
assert_contains "$error_file" '^Batch 66 database output was inconsistent\.$' 'detail count above finding count is rejected generically'

assert_contains "$runbook" '16 kolom wajib' 'runbook documents the sixteen-column contract'
assert_contains "$runbook" 'DELETE RESTRICT.*UPDATE RESTRICT' 'runbook documents expected FK actions'
assert_contains "$runbook" 'marker terakhir' 'runbook documents the terminal END marker'
assert_contains "$runbook" 'sebelum mencetak.*data finding' 'runbook documents schema-output priority truthfully'

set +e
MYSQL_DEFAULTS_EXTRA_FILE="$option_file" MYSQL_CLIENT="$fake_client" "$wrapper" unexpected > "$output_file" 2> "$error_file"
argument_status=$?
set -e
assert_eq '64' "$argument_status" 'unexpected argument maps to exit 64'

set +e
MYSQL_DEFAULTS_EXTRA_FILE="$option_file" MYSQL_CLIENT='unsafe-client-name' "$wrapper" > "$output_file" 2> "$error_file"
client_argument_status=$?
set -e
assert_eq '64' "$client_argument_status" 'unsafe client selector maps to exit 64'

set +e
env -u MYSQL_DEFAULTS_EXTRA_FILE MYSQL_CLIENT="$fake_client" "$wrapper" > "$output_file" 2> "$error_file"
missing_option_status=$?
set -e
assert_eq '66' "$missing_option_status" 'missing option file maps to exit 66'

in_repo_option="${repo_root}/tools/sql/2026-09-03_extra_group_mapping_preflight.sql"
set +e
MYSQL_DEFAULTS_EXTRA_FILE="$in_repo_option" MYSQL_CLIENT="$fake_client" "$wrapper" > "$output_file" 2> "$error_file"
repo_option_status=$?
set -e
assert_eq '66' "$repo_option_status" 'in-repository option file maps to exit 66'
assert_not_contains "$error_file" "$repo_root|client\.cnf|OPTION_FILE_SECRET" 'option-file errors do not print paths or values'

repo_option_symlink="${tmp_dir}/repo-option-symlink.cnf"
ln -s "$in_repo_option" "$repo_option_symlink"
set +e
MYSQL_DEFAULTS_EXTRA_FILE="$repo_option_symlink" MYSQL_CLIENT="$fake_client" "$wrapper" > "$output_file" 2> "$error_file"
repo_option_symlink_status=$?
set -e
assert_eq '66' "$repo_option_symlink_status" 'symlink to an in-repository option file maps to exit 66'

minimal_path="${tmp_dir}/minimal-path"
mkdir "$minimal_path"
ln -s "$(command -v bash)" "${minimal_path}/bash"
ln -s "$(command -v dirname)" "${minimal_path}/dirname"
if command -v realpath >/dev/null 2>&1; then
    ln -s "$(command -v realpath)" "${minimal_path}/realpath"
else
    ln -s "$(command -v readlink)" "${minimal_path}/readlink"
fi
set +e
PATH="$minimal_path" MYSQL_DEFAULTS_EXTRA_FILE="$option_file" MYSQL_CLIENT='mysql' /usr/bin/bash "$wrapper" > "$output_file" 2> "$error_file"
unavailable_status=$?
set -e
assert_eq '127' "$unavailable_status" 'unavailable named client maps to exit 127'

find "$repo_root" -path "$repo_root/.git" -prune -o -type f -printf '%P\n' | LC_ALL=C sort > "$repo_names_after"
sha256sum "$wrapper" "$sql_file" "$runbook" "${BASH_SOURCE[0]}" > "$batch_hash_after"
if ! cmp -s "$repo_names_before" "$repo_names_after"; then
    printf 'FAIL: smoke execution changed the repository file set.\n' >&2
    exit 1
fi
pass
if ! cmp -s "$batch_hash_before" "$batch_hash_after"; then
    printf 'FAIL: smoke execution changed a Batch 66 source file.\n' >&2
    exit 1
fi
pass

printf 'PASS: Batch 66 Extra Group mapping preflight smoke (%d checks).\n' "$checks"
