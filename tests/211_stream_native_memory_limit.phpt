--TEST--
selectStream charges retained native String payloads against memory_limit
--EXTENSIONS--
clickhouse
--INI--
memory_limit=16M
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());
try {
    $c->selectStream(
        "SELECT repeat('x', 999999) AS payload FROM numbers(32)"
    );
    echo "accepted\n";
} catch (ClickHouseException $e) {
    echo strpos($e->getMessage(), "memory_limit") !== false
        ? "rejected\n"
        : "other-error\n";
}
echo $c->ping() ? "ping=ok\n" : "ping=fail\n";
?>
--EXPECT--
rejected
ping=ok
