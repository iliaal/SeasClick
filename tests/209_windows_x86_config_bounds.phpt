--TEST--
Unsigned client-option bounds remain usable on 32-bit PHP
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php
if (PHP_INT_SIZE !== 4) {
    print "skip 32-bit PHP only";
}
?>
--FILE--
<?php
try {
    new ClickHouse([
        "host" => "127.0.0.1",
        "port" => 1,
        "retry_count" => 0,
        "receive_timeout_ms" => 0,
        "send_timeout_ms" => 0,
        "connect_timeout_ms" => 1,
    ]);
    echo "connected\n";
} catch (ClickHouseException $e) {
    echo strpos($e->getMessage(), "out of range") === false
        ? "reached-client\n"
        : "bad-bound\n";
}
?>
--EXPECT--
reached-client
