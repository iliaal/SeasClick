--TEST--
ClickHouse ping() during an open streaming insert is rejected, insert survives
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

// The wire remains in insert mode between calls, while query_active does not.

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.cr155");
$c->execute("CREATE TABLE test.cr155 (a UInt32) ENGINE = Memory");

$c->writeStart("test.cr155", ["a"]);

try {
    $c->ping();
    echo "ping: no throw\n";
} catch (ClickHouseException $e) {
    echo "ping: ", $e->getMessage(), "\n";
}

$c->write([[1], [2], [3]]);
$c->writeEnd();

$n = $c->select("SELECT count() FROM test.cr155", [], ClickHouse::FETCH_ONE);
echo "rows: $n\n";

var_dump($c->ping());

$c->execute("DROP TABLE test.cr155");
?>
--EXPECT--
ping: The insert operation is now in progress
rows: 3
bool(true)
