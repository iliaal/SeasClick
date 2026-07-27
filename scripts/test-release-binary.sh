#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
TEMP_DIR=$(mktemp -d)
readonly SCRIPT_DIR
readonly TEMP_DIR
trap 'rm -rf "${TEMP_DIR}"' EXIT

mkdir -p "${TEMP_DIR}/bin"
touch "${TEMP_DIR}/clickhouse.so"

printf '%s\n' \
	'#!/usr/bin/env bash' \
	'cat <<EOF' \
	'Load command 1' \
	'      cmd LC_BUILD_VERSION' \
	'  cmdsize 32' \
	' platform 1' \
	"    minos \${FAKE_MINOS:-12.0}" \
	'      sdk 15.5' \
	'   ntools 1' \
	'Load command 2' \
	'      cmd LC_SOURCE_VERSION' \
	'  cmdsize 16' \
	'  version 1167.5.0' \
	'EOF' >"${TEMP_DIR}/bin/otool"
chmod +x "${TEMP_DIR}/bin/otool"

output=$(
	PATH="${TEMP_DIR}/bin:${PATH}" \
		"${SCRIPT_DIR}/check-release-binary.sh" \
		--macos-min 12.0 "${TEMP_DIR}/clickhouse.so"
)
[[ "${output}" == "macOS baseline: required=12.0 maximum=12.0" ]]

if PATH="${TEMP_DIR}/bin:${PATH}" FAKE_MINOS=13.0 \
	"${SCRIPT_DIR}/check-release-binary.sh" \
	--macos-min 12.0 "${TEMP_DIR}/clickhouse.so" \
	>"${TEMP_DIR}/negative.stdout" 2>"${TEMP_DIR}/negative.stderr"; then
	printf 'Expected an excessive deployment target to fail\n' >&2
	exit 1
fi
grep -Fxq \
	'macOS deployment target 13.0 exceeds release baseline 12.0' \
	"${TEMP_DIR}/negative.stderr"

printf 'release binary checker self-test passed\n'
