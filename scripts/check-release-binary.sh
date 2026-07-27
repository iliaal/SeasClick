#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
	printf 'Usage: %s <--linux-glibc|--macos-min> <maximum-version> <binary>\n' \
		"${0##*/}" >&2
	exit 64
}

compare_versions() {
	awk -v left="${1}" -v right="${2}" '
    BEGIN {
      split(left, l, ".");
      split(right, r, ".");
      count = length(l) > length(r) ? length(l) : length(r);
      for (i = 1; i <= count; ++i) {
        lv = l[i] + 0;
        rv = r[i] + 0;
        if (lv > rv) {
          print "gt";
          exit;
        }
        if (lv < rv) {
          print "le";
          exit;
        }
      }
      print "le";
    }
  '
}

highest_symbol_version() {
	local -r prefix="${1}"
	local -r binary="${2}"
	readelf --version-info "${binary}" |
		awk -v prefix="${prefix}" '
			$0 ~ ("Name: " prefix "_[0-9]") { sub("^" prefix "_", "", $3); print $3 }
		' |
		sort -V |
		tail -n 1
}

check_linux_glibc() {
	local -r maximum="${1}"
	local -r binary="${2}"
	local comparison
	local required

	command -v readelf >/dev/null 2>&1 || {
		printf 'readelf is required for Linux compatibility inspection\n' >&2
		exit 69
	}
	required=$(highest_symbol_version GLIBC "${binary}")
	[[ -n "${required}" ]] || {
		printf 'No GLIBC symbol requirements found in %s\n' "${binary}" >&2
		exit 65
	}
	comparison=$(
		set -e
		compare_versions "${required}" "${maximum}"
	)
	if [[ "${comparison}" == "gt" ]]; then
		printf 'GLIBC_%s exceeds release baseline GLIBC_%s\n' \
			"${required}" "${maximum}" >&2
		exit 1
	fi
	printf 'GLIBC baseline: required=%s maximum=%s\n' "${required}" "${maximum}"

	# libstdc++ is the more likely ceiling for a C++17 extension, and its
	# symbol versions are independent of glibc's. Report them so a bump in
	# either runtime is visible; GLIBCXX_3.4.30 ships with GCC 12 (Ubuntu
	# 22.04), which is the same baseline the GLIBC ceiling encodes.
	local cxx_required
	for prefix in GLIBCXX CXXABI; do
		cxx_required=$(highest_symbol_version "${prefix}" "${binary}")
		if [[ -n "${cxx_required}" ]]; then
			printf '%s baseline: required=%s\n' "${prefix}" "${cxx_required}"
		fi
	done
}

check_macos_minimum() {
	local -r maximum="${1}"
	local -r binary="${2}"
	local comparison
	local versions
	local version
	local version_list

	command -v otool >/dev/null 2>&1 || {
		printf 'otool is required for macOS compatibility inspection\n' >&2
		exit 69
	}
	versions=$(
		otool -l "${binary}" |
			awk '
        $1 == "cmd" {
          load_command = $2;
          next;
        }
        load_command == "LC_BUILD_VERSION" && $1 == "minos" {
          print $2;
          next;
        }
        load_command ~ /^LC_VERSION_MIN_/ && $1 == "version" {
          print $2;
        }
      '
	)
	[[ -n "${versions}" ]] || {
		printf 'No macOS deployment target found in %s\n' "${binary}" >&2
		exit 65
	}
	while IFS= read -r version; do
		comparison=$(
			set -e
			compare_versions "${version}" "${maximum}"
		)
		if [[ "${comparison}" == "gt" ]]; then
			printf 'macOS deployment target %s exceeds release baseline %s\n' \
				"${version}" "${maximum}" >&2
			exit 1
		fi
	done <<<"${versions}"
	version_list=$(printf '%s\n' "${versions}" | sort -u | paste -sd, -)
	printf 'macOS baseline: required=%s maximum=%s\n' \
		"${version_list}" "${maximum}"
}

main() {
	[[ $# -eq 3 ]] || usage
	local -r mode="${1}"
	local -r maximum="${2}"
	local -r binary="${3}"

	[[ "${maximum}" =~ ^[0-9]+([.][0-9]+)*$ ]] || usage
	[[ -f "${binary}" ]] || {
		printf 'Binary not found: %s\n' "${binary}" >&2
		exit 66
	}

	case "${mode}" in
		--linux-glibc) check_linux_glibc "${maximum}" "${binary}" ;;
		--macos-min) check_macos_minimum "${maximum}" "${binary}" ;;
		*) usage ;;
	esac
}

main "${@}"
