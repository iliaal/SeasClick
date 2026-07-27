# php_clickhouse benchmarks

Compares `php_clickhouse` (LZ4, ZSTD, uncompressed) against
[smi2/phpClickHouse](https://github.com/smi2/phpClickHouse), a
pure-PHP HTTP client.

## Run

```sh
cd bench
composer update --no-dev          # installs phpClickHouse
CLICKHOUSE_HOST=127.0.0.1 \
CLICKHOUSE_PORT=9000 \
CLICKHOUSE_HTTP_PORT=8123 \
CLICKHOUSE_USER=test \
CLICKHOUSE_PASSWD=test \
php -d extension=../modules/clickhouse.so bench_mark.php
```

The runner uses one `Memory` table per client. Table creation, truncation,
and an initial warm-up are outside the measured interval. Each result is
the median of four runs; client order rotates on every run so each client
occupies every order position once. A measured run contains one bulk
insert followed by the stated number of selects.

Set `BENCH_REPETITIONS` to change the sample count. Multiples of four
preserve equal client-order exposure. `BENCH_SMOKE=1` runs a small
two-sample connectivity and runner check.

The composer dependencies need `ext-curl`, `ext-mbstring`, `ext-phar`,
`ext-tokenizer`. Use a stock distro PHP for the benchmark run if your
dev PHP is built with `--disable-all`.

`tests/` runs the functional suite; this directory is performance-only
and deliberately separate.

## Results

Latest run lives in the top-level [README.md](../README.md) under
"Benchmarks". To update it after a code change, run the default matrix
on an otherwise idle host and replace the table with the emitted Markdown.
