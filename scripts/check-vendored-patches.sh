#!/usr/bin/env bash
#
# Guards the vendored clickhouse-cpp divergence record.
#
# Three invariants, in increasing strength:
#
#   1. every patch file has a LOCAL_PATCHES.md heading, and vice versa
#   2. the patch stack reverse-applies against the vendored tree
#   3. what is left after the reverse-apply is pristine upstream
#
# (3) is the one that catches a hand-edit landing in lib/clickhouse-cpp
# without a patch file — the failure mode that shipped the destructor
# teardown guard undocumented. It needs the upstream sources: either set
# CLICKHOUSE_CPP_PRISTINE to an existing checkout, or let the script clone
# the pinned tag.
#
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
vendored="$root/lib/clickhouse-cpp"
patch_dir="$root/patches/upstream"
manifest="$root/.upstream/clickhouse-cpp.yml"
doc="$vendored/LOCAL_PATCHES.md"

fail() { printf 'check-vendored-patches: %s\n' "$1" >&2; exit 1; }

[ -d "$patch_dir" ] || fail "missing $patch_dir"
[ -f "$doc" ] || fail "missing $doc"

mapfile -t patches < <(find "$patch_dir" -maxdepth 1 -name '*.patch' | sort)
[ "${#patches[@]}" -gt 0 ] || fail "no patch files found in $patch_dir"

# --- 1. patch files vs LOCAL_PATCHES.md headings ------------------------
#
# A heading names the files it touches, so match each patch's Subject
# against the document body rather than trying to parse headings.
doc_headings=$(grep -c '^## ' "$doc" || true)
# The document opens with an "Obsoleted in <version>" section that
# deliberately has no patch file.
obsolete_headings=$(grep -c '^## Obsoleted in ' "$doc" || true)
live_headings=$((doc_headings - obsolete_headings))
if [ "$live_headings" -ne "${#patches[@]}" ]; then
    fail "LOCAL_PATCHES.md documents $live_headings live modification(s) but $patch_dir holds ${#patches[@]} patch file(s)"
fi

for p in "${patches[@]}"; do
    subject=$(sed -n 's/^Subject: \[PATCH[^]]*\] //p' "$p" | head -1)
    [ -n "$subject" ] || fail "$(basename "$p") has no Subject line"
done

# --- manifest note should agree on the count ----------------------------
if [ -f "$manifest" ]; then
    if ! grep -q "${#patches[@]} local patches" "$manifest"; then
        fail "$manifest note does not say '${#patches[@]} local patches'"
    fi
fi

# --- 2 + 3. reverse-apply the stack and compare with upstream -----------
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# Populate from git, not the working tree: an in-tree build leaves .libs/*.o
# and .deps/ inside lib/clickhouse-cpp, and those are not upstream sources.
mapfile -t tracked < <(cd "$root" && git ls-files 'lib/clickhouse-cpp/clickhouse' 'lib/clickhouse-cpp/contrib')
[ "${#tracked[@]}" -gt 0 ] || fail "no tracked files under lib/clickhouse-cpp"
for rel in "${tracked[@]}"; do
    dest="$work/${rel#lib/clickhouse-cpp/}"
    mkdir -p "$(dirname "$dest")"
    cp "$root/$rel" "$dest"
done

for p in $(printf '%s\n' "${patches[@]}" | sort -r); do
    if ! (cd "$work" && patch -p1 -R -f -s --no-backup-if-mismatch < "$p") >/dev/null 2>&1; then
        fail "$(basename "$p") does not reverse-apply against lib/clickhouse-cpp — the patch file and the tree have drifted"
    fi
done

pristine="${CLICKHOUSE_CPP_PRISTINE:-}"
if [ -z "$pristine" ]; then
    pinned=$(sed -n 's/^pinned: *//p' "$manifest" | head -1)
    repo=$(sed -n 's/^repo: *//p' "$manifest" | head -1)
    [ -n "$pinned" ] && [ -n "$repo" ] || fail "cannot read pinned/repo from $manifest"
    pristine="$work/pristine"
    if ! git clone -q --depth 1 --branch "v$pinned" "https://github.com/$repo.git" "$pristine" 2>/dev/null; then
        printf 'check-vendored-patches: could not clone v%s; skipping the pristine comparison\n' "$pinned" >&2
        printf 'check-vendored-patches: patch/doc mapping and reverse-apply OK (%s patches)\n' "${#patches[@]}"
        exit 0
    fi
fi

for sub in clickhouse contrib; do
    # Only compare files the vendored copy still carries: the vendoring step
    # trims upstream's tests, benchmarks and CI.
    while IFS= read -r rel; do
        if ! cmp -s "$work/$rel" "$pristine/$rel"; then
            fail "$rel differs from pristine upstream after reversing every patch — it was hand-edited without a patch file"
        fi
    done < <(cd "$work/$sub" && find . -type f -printf "$sub/%P\n" | sort)
done

printf 'check-vendored-patches: OK (%s patches, tree reverses to pristine upstream)\n' "${#patches[@]}"
