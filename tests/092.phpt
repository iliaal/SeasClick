--TEST--
ClickHouse server-side EndInsert() failure leaves the client usable, no manual resetConnection
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.insert_constraint");
$c->execute("CREATE TABLE test.insert_constraint (id UInt32, CONSTRAINT positive CHECK id > 0) ENGINE=Memory");

try {
    $c->insert("test.insert_constraint", ["id"], [[1], [2], [0], [3]]);
    echo "direct insert: NO THROW\n";
} catch (ClickHouseException $e) {
    echo "direct insert: REJECTED\n";
}
$x = $c->select("SELECT 41 AS x", [], ClickHouse::FETCH_ONE);
echo "select after direct insert error: $x\n";

// The CHECK constraint is evaluated at writeEnd, after block transmission.
$c->writeStart("test.insert_constraint", ["id"]);
$c->write([[1], [2]]);
$c->write([[0]]);
try {
    $c->writeEnd();
    echo "writeEnd: NO THROW\n";
} catch (ClickHouseException $e) {
    echo "writeEnd: REJECTED\n";
}
$x = $c->select("SELECT 42 AS x", [], ClickHouse::FETCH_ONE);
echo "select after writeEnd error: $x\n";

$c->writeStart("test.insert_constraint", ["id"]);
$c->write([[10], [20], [30]]);
$c->writeEnd();
$cnt = $c->select("SELECT count() FROM test.insert_constraint", [], ClickHouse::FETCH_ONE);
echo "rowcount after fresh cycle: $cnt\n";

$c->execute("DROP TABLE test.insert_constraint");
?>
--EXPECT--
direct insert: REJECTED
select after direct insert error: 41
writeEnd: REJECTED
select after writeEnd error: 42
rowcount after fresh cycle: 3
