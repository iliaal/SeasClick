#!/usr/bin/env bash

set -Eeuo pipefail
shopt -s inherit_errexit

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
readonly ROOT
FIXTURE="${ROOT}/scripts/.naming-guard-negative.php"
readonly FIXTURE
trap 'rm -f -- "${FIXTURE}"' EXIT

legacy_name='Seas''Click'
printf '<?php // unintended %s reference\n' "${legacy_name}" >"${FIXTURE}"

guard="${ROOT}/scripts/check-no-""seas""click.sh"
if "${guard}" >/dev/null 2>&1; then
	printf '%s\n' "::error::Naming guard accepted a legacy reference in scripts/." >&2
	exit 1
fi

rm -f -- "${FIXTURE}"
"${guard}"
