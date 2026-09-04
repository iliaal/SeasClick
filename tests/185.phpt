--TEST--
ClickHouse constructor rejects values wider than native option sinks
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";
$base = clickhouse_test_config();
$is64 = PHP_INT_SIZE > 4;
$probes = [
    "retry_count" => 4294967296,
    "connect_timeout_ms" => 2147483648,
    "receive_timeout_ms" => 4294967296,
    "send_timeout_ms" => 4294967296,
    "connect_timeout" => 2147484,
    "receive_timeout" => 4294968,
    "send_timeout" => 4294968,
    "tcp_keepalive_idle" => 2147483648,
    "tcp_keepalive_intvl" => 2147483648,
    "tcp_keepalive_cnt" => 2147483648,
    "max_compression_chunk_size" => 2147483648,
];
// Every probe above exceeds its 64-bit bound, so 64-bit PHP must reject
// all of them. On 32-bit PHP the retry_count bound narrows to
// min(UINT_MAX, ZEND_LONG_MAX) and the 2^32 probe arrives as a float,
// making that one outcome platform-defined; its 32-bit bound direction
// is covered by 209_windows_x86_config_bounds instead.
$want = [];
foreach ($probes as $key => $value) {
    $want[$key] = (!$is64 && $key === "retry_count") ? "deferred" : "rejected";
}

foreach ($probes as $key => $value) {
    try {
        new ClickHouse([$key => $value] + $base);
        $actual = "accepted";
    } catch (ClickHouseException $e) {
        $actual = "rejected";
    }
    echo $key, ": ", ($want[$key] === "deferred" || $actual === $want[$key]) ? "ok" : "MISMATCH($actual)", "\n";
}

new ClickHouse([
    "retry_count" => 2,
    "connect_timeout_ms" => 1000,
    "receive_timeout_ms" => 1000,
    "send_timeout_ms" => 1000,
    "tcp_keepalive_idle" => 60,
    "tcp_keepalive_intvl" => 5,
    "tcp_keepalive_cnt" => 3,
    "max_compression_chunk_size" => 65535,
] + $base);
echo "ordinary values: accepted\n";
?>
--EXPECT--
retry_count: ok
connect_timeout_ms: ok
receive_timeout_ms: ok
send_timeout_ms: ok
connect_timeout: ok
receive_timeout: ok
send_timeout: ok
tcp_keepalive_idle: ok
tcp_keepalive_intvl: ok
tcp_keepalive_cnt: ok
max_compression_chunk_size: ok
ordinary values: accepted
