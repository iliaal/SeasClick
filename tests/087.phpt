--TEST--
ClickHouse write() conversion failure leaves the client usable without resetConnection
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.write_recover");
$c->execute("CREATE TABLE test.write_recover (id UInt32) ENGINE=Memory");

$c->writeStart("test.write_recover", ["id"]);

try {
    // -1 against UInt32 trips the unsigned-bound check in zvalToBlock.
    $c->write([[-1]]);
    echo "write: NO THROW\n";
} catch (ClickHouseException $e) {
    echo "write: REJECTED\n";
}

$x = $c->select("SELECT 42 AS x", [], ClickHouse::FETCH_ONE);
echo "select after failed write: $x\n";

$c->writeStart("test.write_recover", ["id"]);
$c->write([[1], [2], [3]]);
$c->writeEnd();
$cnt = $c->select("SELECT count() FROM test.write_recover", [], ClickHouse::FETCH_ONE);
echo "rowcount: $cnt\n";

$c->execute("DROP TABLE test.write_recover");
?>
--EXPECT--
write: REJECTED
select after failed write: 42
rowcount: 3
