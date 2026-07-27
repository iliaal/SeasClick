#!/usr/bin/env bash
#
# Build and run the phpt suite against a 32-bit PHP.
#
# The CI matrix is 64-bit everywhere except the Windows x86 lane, and that
# lane cannot reach a ClickHouse server, so every SIZEOF_ZEND_LONG < 8 branch
# ships unexercised. This reproduces a real 32-bit run locally through an
# i386 container.
#
#   ./scripts/run-tests-32bit.sh                 # whole suite
#   ./scripts/run-tests-32bit.sh tests/208*.phpt # selected tests
#
# Needs Docker with linux/386 emulation and a ClickHouse server the container
# can reach (defaults to the host's, via --network host).
#
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
image="${PHP32_IMAGE:-i386/debian:bookworm}"
host="${CLICKHOUSE_HOST:-127.0.0.1}"
port="${CLICKHOUSE_PORT:-9000}"
user="${CLICKHOUSE_USER:-test}"
passwd="${CLICKHOUSE_PASSWD:-test}"

command -v docker >/dev/null 2>&1 || {
    echo "run-tests-32bit: docker is required" >&2
    exit 69
}

tarball=$(mktemp -t php-clickhouse-32bit-XXXXXX.tar)
trap 'rm -f "$tarball"' EXIT

# Archive the index, not the working tree: a native in-tree build leaves a
# Makefile full of absolute host paths that breaks the container build.
(cd "$root" && git archive --format=tar "$(git write-tree)") > "$tarball"

targets=("$@")
[ "${#targets[@]}" -gt 0 ] || targets=(tests/)

docker run --rm --platform linux/386 --network host \
    -v "$tarball:/src.tar:ro" \
    -e "CLICKHOUSE_HOST=$host" \
    -e "CLICKHOUSE_PORT=$port" \
    -e "CLICKHOUSE_USER=$user" \
    -e "CLICKHOUSE_PASSWD=$passwd" \
    -e NO_INTERACTION=1 \
    "$image" bash -c '
set -e
apt-get update -qq >/dev/null
apt-get install -y -qq php-cli php-dev g++ make >/dev/null 2>&1
mkdir -p /work && cd /work && tar xf /src.tar
php -r "if (PHP_INT_SIZE !== 4) { fwrite(STDERR, \"not a 32-bit PHP\n\"); exit(1); }"
php -r "echo \"PHP \", PHP_VERSION, \" (PHP_INT_SIZE=\", PHP_INT_SIZE, \")\n\";"
phpize >/dev/null 2>&1
./configure --enable-clickhouse >/dev/null 2>&1
make -j"$(nproc)" >/dev/null 2>&1
echo "build ok"
TEST_PHP_EXECUTABLE=$(which php) exec php run-tests.php \
    -d extension=/work/modules/clickhouse.so '"$*"'
'
